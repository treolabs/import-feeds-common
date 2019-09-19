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

use Treo\Core\Container;

/**
 * Class AbstractConverter
 *
 * @author r.ratsun <r.ratsun@treolabs.com>
 */
abstract class AbstractConverter
{
    /**
     * @var Container
     */
    protected $container;

    /**
     * AbstractConverter constructor.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @param \stdClass $inputRow
     * @param string    $entityType
     * @param array     $config
     * @param array     $row
     * @param string    $delimiter
     *
     * @return mixed
     */
    abstract public function convert(\stdClass $inputRow, string $entityType, array $config, array $row, string $delimiter);
}
