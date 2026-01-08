<?php

declare(strict_types=1);

/*
 * This file is part of the HexagonalMakerBundle package.
 *
 * (c) Ahmed EBEN HASSINE <ahmedbhs123@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace AhmedBhs\HexagonalMakerBundle\Analyzer;

/**
 * Enum representing different command patterns
 */
enum CommandPattern: string
{
    case CREATE = 'create';
    case UPDATE = 'update';
    case DELETE = 'delete';
    case CREATE_RELATION = 'create_relation';
    case ACTIVATE = 'activate';
    case DEACTIVATE = 'deactivate';
    case CHANGE_STATUS = 'change_status';
    case CUSTOM = 'custom';
}
