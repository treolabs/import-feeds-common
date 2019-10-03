<?php

declare(strict_types=1);

namespace Import\Types\Simple\Handlers;

use Espo\Core\ORM\EntityManager;
use Espo\ORM\Entity;
use Treo\Core\Container;
use Treo\Core\ServiceFactory;
use Treo\Core\Utils\Metadata;
use Treo\Core\Utils\Util;
use Espo\Core\Exceptions\Error;

/**
 * Class AbstractHandler
 *
 * @author r.zablodskiy@treolabs.com
 */
abstract class AbstractHandler
{
    /**
     * @var Container
     */
    protected $container;

    /**
     * @var array
     */
    protected $created = [];

    /**
     * @var array
     */
    protected $updated = [];

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
    protected function getIdRow(array $configuration, string $idField): ?array
    {
        foreach ($configuration as $row) {
            if ($row['name'] == $idField) {
                return $row;
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
        // get converter
        $converter = $this
            ->getMetadata()
            ->get(['import', 'simple', 'fields', $this->getType($entityType, $item), 'converter']);

        // delegate
        if (!empty($converter)) {
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
     * @param \stdClass $restore
     * @param Entity $entity
     * @param array $item
     */
    protected function prepareValue(\stdClass $restore, Entity $entity, array $item)
    {
        // get converter
        $converter = $this
            ->getMetadata()
            ->get(['import', 'simple', 'fields', $this->getType($entity->getEntityType(), $item), 'converter']);

        // delegate
        if (!empty($converter)) {
            return (new $converter($this->container))->prepareValue($restore, $entity, $item);
        }

        $restore->{$item['name']} = $entity->get($item['name']);
    }

    /**
     * @param string $entityType
     * @param array  $item
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
     * @param string $importResultId
     * @param array $data
     *
     * @throws \Espo\Core\Exceptions\Error
     */
    protected function saveRestoreData(string $importResultId, array $data)
    {
        if (!empty($importResult = $this->getEntityManager()->getEntity('ImportResult', $importResultId))) {
            $importResult->set('restoreData', $data);
            $this->getEntityManager()->saveEntity($importResult);
        }
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

    /**
     * @param \stdClass $restoreRow
     * @param Entity $entity
     * @param array $item
     * @param string $delimiter
     */
    protected function convertRestore(\stdClass $restoreRow, Entity $entity, array $item, string $delimiter)
    {
        // get converter
        $converter = $this
            ->getMetadata()
            ->get(['import', 'simple', 'fields', $this->getType($entity->getEntityType(), $item), 'converter']);

        // delegate
        if (!empty($converter)) {
            return (new $converter($this->container))->revert($restoreRow, $entity, $item, $delimiter);
        } else {
            $value = $entity->get($item['name']);
        }

        // set
        $restoreRow->{$item['column']} = $value;
    }

    /**
     * @param string $importResultId
     * @param array $conf
     * @throws Error
     */
    protected function saveRestore(string $importResultId, array $conf)
    {
        // prepare import result
        $importResult = $this->getEntityManager()->getEntity('ImportResult', $importResultId);

        $importResult->set('created', $this->created);
        $importResult->set('updated', $this->updated);
        $importResult->set('configuration', $conf);

        $this->getEntityManager()->saveEntity($importResult);
    }
}