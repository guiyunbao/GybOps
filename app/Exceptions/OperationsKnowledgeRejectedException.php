<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * 运营知识入库被合规规则拒绝。
 */
final class OperationsKnowledgeRejectedException extends RuntimeException
{
    /**
     * @param  list<string>  $violations
     */
    public function __construct(public readonly array $violations)
    {
        parent::__construct('operations_knowledge_rejected:'.implode(',', $violations));
    }
}
