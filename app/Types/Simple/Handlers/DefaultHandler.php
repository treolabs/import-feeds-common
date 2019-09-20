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
        // get prepared rows
        if (empty($rows = $this->prepareRows($fileData, $data))) {
            return true;
        }

        // prepare entity type
        $entityType = (string)$data['data']['entity'];

        // prepare import result id
        $importResultId = (string)$data['data']['importResultId'];

        // create service
        $service = $this->getServiceFactory()->create($entityType);

        // save
        foreach ($rows as $input) {
            // prepare entity
            $entity = null;

            // prepare id
            $id = $input->_id;
            unset($input->_id);

            // prepare file row
            $fileRow = (string)$input->_fileRow;
            unset($input->_fileRow);

            // prepare action
            $action = (empty($id)) ? 'create' : 'update';

            try {
                // begin transaction
                $this->getEntityManager()->getPDO()->beginTransaction();

                if (empty($id)) {
                    $entity = $service->createEntity($input);
                } else {
                    $entity = $service->updateEntity($id, $input);
                }

                $this->getEntityManager()->getPDO()->commit();
            } catch (\Throwable $e) {
                $this->getEntityManager()->getPDO()->rollBack();
                $this->log($entityType, $importResultId, 'error', $fileRow, $e->getMessage());
            }
            if (!is_null($entity)) {
                $this->log($entityType, $importResultId, $action, $fileRow, $entity->get('id'));
            }
        }

        return true;
    }

    /**
     * Prepare rows for saving
     *
     * @param array $fileData
     * @param array $data
     *
     * @return array
     * @throws Error
     */
    protected function prepareRows(array $fileData, array $data): array
    {
        // prepare result
        $result = [];

        // prepare entity type
        $entityType = (string)$data['data']['entity'];

        // prepare id field
        $idField = isset($data['data']['idField']) ? $data['data']['idField'] : null;

        // find ID row
        if (!empty($idRow = $this->getIdRow($data['data']['configuration'], $idField))) {
            // find exists
            $exists = $this->getExists($entityType, $idRow['name'], array_column($fileData, $idRow['column']));
        }

        // prepare file row
        $fileRow = (int)$data['offset'];

        foreach ($fileData as $row) {
            // increment file row number
            $fileRow++;

            // create row
            $inputRow = new \stdClass();
            $inputRow->_fileRow = $fileRow;
            $inputRow->_id = (isset($exists[$row[$idRow['column']]])) ? $exists[$row[$idRow['column']]] : null;

            // prepare row
            foreach ($data['data']['configuration'] as $item) {
                $this->convertItem($inputRow, $entityType, $item, $row, $data['data']['delimiter']);
            }

            // push to result
            if ($data['action'] == 'create') {
                // set id as null
                $inputRow->_id = null;

                $result[] = $inputRow;
            }
            if ($data['action'] == 'update' && !empty($inputRow->_id)) {
                $result[] = $inputRow;
            }
            if ($data['action'] == 'create_update') {
                $result[] = $inputRow;
            }
        }

        return $result;
    }
}