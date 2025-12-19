<?php

declare(strict_types=1);

namespace Tourze\DoctrineResolveTargetEntityBundle\Exception;

/**
 * 无效接口异常
 *
 * 当接口不存在或无效时抛出
 */
final class InvalidInterfaceException extends \InvalidArgumentException
{
}
