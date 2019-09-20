<?php

declare(strict_types=1);

namespace Import\Types\Simple\Handlers;

use Espo\Core\ORM\EntityManager;
use Espo\ORM\Entity;
use Espo\Core\Exceptions\Error;
use Treo\Core\Container;
use Treo\Core\ServiceFactory;
use Treo\Core\Utils\Metadata;
use Treo\Core\Utils\Util;

/**
 * Class AbstractHandler
 *
 * @author r.zablodskiy@treolabs.com
 */
abstract class AbstractHandler
{
    /**
     * @var null
     */
    protected $container = null;

    /**
     * AbstractHandler constructor.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @param array $fileData
     * @param array $data
     *
     * @return bool
     */
    abstract public function run(array $fileData, array $data): bool;

    /**
     * @param array  $configuration
     * @param string $idField
     *
     * @return array|null
     */
    protected function getIdRow(array $configuration, string $idField = null): ?array
    {
        if (!empty($idField)) {
            foreach ($configuration as $row) {
                if ($row['name'] == $idField) {
                    return $row;
                }
            }
        }

        return null;
    }

    /**
     * @param string $entityType
     * @param string $name
     * @param array  $ids
     *
     * @return mixed
     */
    protected function getExists(string $entityType, string $name, array $ids): array
    {
        // get data
        $data = $this
            ->getEntityManager()
            ->getRepository($entityType)
            ->select(['id', $name])
            ->where([$name => $ids])
            ->find();

        $result = [];

        if (count($data) > 0) {
            foreach ($data as $entity) {
                $result[$entity->get($name)] = $entity->get('id');
            }
        }

        return $result;
    }

    /**
     * @param \stdClass $inputRow
     * @param string    $entityType
     * @param array     $item
     * @param array     $row
     * @param string    $delimiter
     */
    protected function convertItem(\stdClass $inputRow, string $entityType, array $item, array $row, string $delimiter)
    {
        // delegate
        if (!empty($converter
            = $this->getMetadata()->get(['import', 'simple', 'fields', $this->getType($entityType, $item), 'converter']))) {
            return (new $converter($this->container))->convert($inputRow, $entityType, $item, $row, $delimiter);
        }

        // prepare value
        if (is_null($item['column']) || empty($row[$item['column']])) {
            $value = $item['default'];
            if (!empty($value) && is_string($value)) {
                $value = str_replace("{{hash}}", Util::generateId(), $value);
            }
        } else {
            $value = $row[$item['column']];
        }

        // set
        $inputRow->{$item['name']} = $value;
    }

    /**
     * @param string $entityType
     * @param array $item
     *
     * @return string|null
     */
    protected function getType(string $entityType, array $item): ?string
    {
        return (string)$this->getMetadata()->get(['entityDefs', $entityType, 'fields', $item['name'], 'type']);
    }

    /**
     * @param string $entityName
     * @param string $importResultId
     * @param string $type
     * @param string $row
     * @param string $data
     *
     * @return Entity
     */
    public function log(string $entityName, string $importResultId, string $type, string $row, string $data): Entity
    {
        // create log
        $log = $this->getEntityManager()->getEntity('ImportResultLog');
        $log->set('name', $row);
        $log->set('rowNumber', $row);
        $log->set('entityName', $entityName);
        $log->set('importResultId', $importResultId);
        $log->set('type', $type);
        if ($type == 'error') {
            $log->set('message', $data);
        } else {
            $log->set('entityId', $data);
        }

        $this->getEntityManager()->saveEntity($log);

        return $log;
    }

    /**
     * @return EntityManager
     */
    protected function getEntityManager(): EntityManager
    {
        return $this->container->get('entityManager');
    }

    /**
     * @param string $entityType
     *
     * @return ServiceFactory
     */
    protected function getServiceFactory(): ServiceFactory
    {
        return $this->container->get('serviceFactory');
    }

    /**
     * @return Metadata
     */
    protected function getMetadata(): Metadata
    {
        return $this->container->get('metadata');
    }
}