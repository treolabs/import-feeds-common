<?php
/**
 * Import
 * TreoPIM Premium Plugin
 * Copyright (c) TreoLabs GmbH
 *
 * This Software is the property of Zinit Solutions GmbH and is protected
 * by copyright law - it is NOT Freeware and can be used only in one project
 * under a proprietary license, which is delivered along with this program.
 * If not, see <http://treopim.com/eula>.
 *
 * This Software is distributed as is, with LIMITED WARRANTY AND LIABILITY.
 * Any unauthorised use of this Software without a valid license is
 * a violation of the License Agreement.
 *
 * According to the terms of the license you shall not resell, sublicense,
 * rent, lease, distribute or otherwise transfer rights or usage of this
 * Software or its derivatives. You may modify the code of this Software
 * for your own needs, if source code is provided.
 */

declare(strict_types=1);

namespace Import\Types\Simple\FieldConverters;

/**
 * Class LinkMultiple
 *
 * @author r.zablodskiy@treolabs.com
 */
class LinkMultiple extends AbstractConverter
{
    /**
     * @inheritDoc
     */
    public function convert(\stdClass $inputRow, string $entityType, array $config, array $row, string $delimiter)
    {
        // prepare value
        $value = explode(',', $config['default']);

        if (!is_null($config['column']) && !empty($row[$config['column']])) {
            $value = explode($delimiter, $row[$config['column']]);

            if ($config['field'] != 'id') {
                // get entity name
                $entityName = $this
                    ->container
                    ->get('metadata')
                    ->get(['entityDefs', $entityType, 'links', $config['name'], 'entity']);

                if (!empty($entityName)) {
                    // find entity
                    $entities = $this
                        ->container
                        ->get('entityManager')
                        ->getRepository($entityName)
                        ->select(['id'])
                        ->where([$config['field'] => $value])
                        ->find()
                        ->toArray();

                    if (count($entities) > 0) {
                        $value = array_column($entities, 'id');
                    }
                }
            }
        }

        $inputRow->{$config['name'] . 'Ids'} = (array)$value;
    }
}