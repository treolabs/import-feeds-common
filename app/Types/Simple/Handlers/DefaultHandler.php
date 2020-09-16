<?php

declare(strict_types=1);

namespace Import\Types\Simple\Handlers;

use Espo\Core\Exceptions\Error;

/**
 * Class DefaultHandler
 *
 * @author r.zablodskiy@treolabs.com
 */
class DefaultHandler extends AbstractHandler
{
    /**
     * @inheritdoc
     *
     * @throws Error
     */
    public function run(array $fileData, array $data): bool
    {
        // prepare entity type
        $entityType = (string)$data['data']['entity'];

        // prepare import result id
        $importResultId = (string)$data['data']['importResultId'];

        // create service
        $service = $this->getServiceFactory()->create($entityType);

        // prepare id field
        $idField = isset($data['data']['idField']) ? $data['data']['idField'] : "";

        // find ID row
        $idRow = $this->getIdRow($data['data']['configuration'], $idField);

        // find exists if it needs
        $exists = [];
        if (in_array($data['action'], ['update', 'create_update']) && !empty($idRow)) {
            $exists = $this->getExists($entityType, $idRow['name'], array_column($fileData, $idRow['column']));
        }

        // prepare file row
        $fileRow = (int)$data['offset'];

        // save
        foreach ($fileData as $row) {
            // increment file row number
            $fileRow++;

            // prepare id
            if ($data['action'] == 'create') {
                $id = null;
            } elseif ($data['action'] == 'update') {
                if (isset($exists[$row[$idRow['column']]])) {
                    $id = $exists[$row[$idRow['column']]];
                } else {
                    // skip row if such item does not exist
                    continue 1;
                }
            } elseif ($data['action'] == 'create_update') {
                $id = (isset($exists[$row[$idRow['column']]])) ? $exists[$row[$idRow['column']]] : null;
            }

            try {
                // prepare entity
                $entity = !empty($id) ? $this->getEntityManager()->getEntity($entityType, $id) : null;

                // begin transaction
                $this->getEntityManager()->getPDO()->beginTransaction();

                // prepare row and data for restore
                $input = new \stdClass();
                $restore = new \stdClass();

                foreach ($data['data']['configuration'] as $item) {
                    if ($item['name'] == 'id') {
                        continue;
                    }

                    $this->convertItem($input, $entityType, $item, $row, $data['data']['delimiter']);

                    if (!empty($entity)) {
                        $this->prepareValue($restore, $entity, $item);
                    }
                }

                $updatedEntity = null;
                if (empty($id)) {
                    $updatedEntity = $service->createEntity($input);

                    $this->saveRestoreRow('created', $entityType, $updatedEntity->get('id'));
                } else {
                    $updatedEntity = $service->updateEntity($id, $input);

                    $this->saveRestoreRow('updated', $entityType, [$id => $restore]);
                }

                $this->getEntityManager()->getPDO()->commit();
            } catch (\Throwable $e) {
                // roll back transaction
                $this->getEntityManager()->getPDO()->rollBack();

                // push log
                $this->log($entityType, $importResultId, 'error', (string)$fileRow, $e->getMessage());
            }

            if (!empty($updatedEntity)) {
                // prepare action
                $action = empty($id) ? 'create' : 'update';

                // push log
                $this->log($entityType, $importResultId, $action, (string)$fileRow, $updatedEntity->get('id'));
            }
        }

        return true;
    }
}