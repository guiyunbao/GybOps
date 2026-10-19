<?php

namespace App\Services\Operations;

/**
 * 用可公开文本替代培训系统同步到 Dify 失败的资料。
 *
 * 失败原因来自培训后台：四份演示文稿返回 HTTP 400，一份日常运输演示文稿超过 50MB。
 * 原文件仍留在培训系统，这里只把可对外摘要写入 GEOFlow。
 */
final class TrainingKnowledgeRepair
{
    public function __construct(private readonly OperationsKnowledgeProvisioner $knowledge) {}

    /**
     * @return list<string>
     */
    public function apply(): array
    {
        $this->knowledge->ensure();
        $applied = [];
        foreach ($this->replacements() as $replacement) {
            $marker = '<!-- training-repair:'.$replacement['file'].' -->';
            $section = $marker."\n".$replacement['body'];
            $this->knowledge->append($replacement['partition'], $section);
            $applied[] = $replacement['file'];
        }

        return $applied;
    }

    /**
     * @return list<array{file:string,partition:string,body:string}>
     */
    public function replacements(): array
    {
        return [
            [
                'file' => '桂运宝公司介绍.pptx',
                'partition' => OperationsKnowledgeCatalog::PUBLIC_SCIENCE,
                'body' => '培训同步修复：公司介绍演示文稿上传 Dify 返回 HTTP 400。对外使用已审核的公司介绍摘要，不搬入演示文稿原件。',
            ],
            [
                'file' => '桂运宝日常运输物流.pptx',
                'partition' => OperationsKnowledgeCatalog::PUBLIC_SCIENCE,
                'body' => '培训同步修复：日常运输演示文稿超过 50MB，Dify 跳过上传。对外使用车型、货类和线路匹配摘要。',
            ],
            [
                'file' => '桂运宝日常运输物料2.pptx',
                'partition' => OperationsKnowledgeCatalog::PUBLIC_SCIENCE,
                'body' => '培训同步修复：日常运输物料演示文稿上传 Dify 返回 HTTP 400。内容并入日常运输摘要。',
            ],
            [
                'file' => '桂运宝居家业务培训讲解版.pptx',
                'partition' => OperationsKnowledgeCatalog::INTERNAL_SCRIPTS,
                'body' => '培训同步修复：居家业务讲解版上传 Dify 返回 HTTP 400。该资料仅内部引用，不进入对外文章。',
            ],
            [
                'file' => '桂运宝商业计划书.pdf',
                'partition' => OperationsKnowledgeCatalog::INTERNAL_SCRIPTS,
                'body' => '培训同步修复：商业计划书上传 Dify 返回 HTTP 400。计划书保持内部，不进入对外科普。',
            ],
        ];
    }
}
