<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * SEO 建议缺少可用的关键词或 Meta 描述。
 */
final class ArticleSeoMetaContractException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('invalid_seo_meta_contract');
    }
}
