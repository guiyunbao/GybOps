<?php

namespace App\Services\Operations;

/**
 * 桂运宝运营知识分区及可公开种子内容。
 */
final class OperationsKnowledgeCatalog
{
    public const PUBLIC_SCIENCE = 'operations_public_science';

    public const INTERNAL_SCRIPTS = 'operations_internal_scripts';

    public const OPERATIONS_BRIEF = 'operations_brief';

    public const COMPLIANCE = 'operations_compliance';

    public const POLICY = 'operations_policy';

    public const MARKET = 'operations_market';

    public const CORRIDOR = 'operations_corridor';

    public const TRADE = 'operations_trade';

    /**
     * @return list<array{key:string,name:string,description:string,risk_level:string,content:string}>
     */
    public function definitions(): array
    {
        return [
            [
                'key' => self::PUBLIC_SCIENCE,
                'name' => '对外科普',
                'description' => '车型、货物、法规解读和可公开的公司介绍。',
                'risk_level' => 'low',
                'content' => $this->publicScience(),
            ],
            [
                'key' => self::INTERNAL_SCRIPTS,
                'name' => '内部话术',
                'description' => '电销与客服手册只供生成时引用，文章不公开原文。',
                'risk_level' => 'medium',
                'content' => $this->internalScripts(),
            ],
            [
                'key' => self::OPERATIONS_BRIEF,
                'name' => '运营摘要',
                'description' => '线路、货类和运价区间的聚合结果，保留生成时间与来源。',
                'risk_level' => 'medium',
                'content' => $this->briefIntro(),
            ],
            [
                'key' => self::COMPLIANCE,
                'name' => '合规红线',
                'description' => '禁止进入知识库和对外文章的字段。',
                'risk_level' => 'low',
                'content' => $this->compliance(),
            ],
            [
                'key' => self::POLICY,
                'name' => '政策合规',
                'description' => '交通部、发改委、政府网和广西交通厅的公开政策列表。每个工作日写入新稿摘要。',
                'risk_level' => 'low',
                'content' => $this->policyIntro(),
            ],
            [
                'key' => self::MARKET,
                'name' => '行情数据',
                'description' => '中国物流与采购网上的公路运价指数稿。每周写入。',
                'risk_level' => 'low',
                'content' => $this->marketIntro(),
            ],
            [
                'key' => self::CORRIDOR,
                'name' => '区域通道',
                'description' => '广西政府和南宁市政府的公开要闻。每个工作日写入。',
                'risk_level' => 'low',
                'content' => $this->corridorIntro(),
            ],
            [
                'key' => self::TRADE,
                'name' => '外贸东盟',
                'description' => '海关总署和中国—东盟合作区的官方稿。当前列表页不能稳定抓取，保留分区待接入。',
                'risk_level' => 'low',
                'content' => $this->tradeIntro(),
            ],
        ];
    }

    public function publicScience(): string
    {
        return <<<'MD'
# 货运车型

常见货运车辆按装载方式分为栏板、厢式、平板、高栏、冷藏、自卸和集装箱牵引。选型先看货物形态、重量和装卸方式，再看线路限高与装卸场地。煤炭、矿石、钢材、水泥、粮食、饲料、化肥和建材属于公司熟悉的大宗货类。

# 八大类货物

大宗货物运输要同时看货类特性、防潮防漏要求和适配车型。散货注意撒漏和计重，袋装货注意堆码和防雨，液体和危化货物不在本文作承诺，遇到特殊资质需求转人工判断。

# 公司介绍

桂运宝从事华南地区大宗物流，服务覆盖广西与珠三角方向的公路运输。对外介绍只使用已经确认的线路、运力和资质，不编造案例和报价。

# 日常运输

日常运输按货类、起讫地和车型匹配运力。内容中只保留聚合后的线路和货类，不写入单票订单。
MD;
    }

    public function internalScripts(): string
    {
        return <<<'MD'
# 内部话术

业务和客服话术只作为生成提示，不把手册原文发布到官网或公众号。对外表达改写成线路、车型和货物知识，联系方式使用官网表单。
MD;
    }

    public function briefIntro(): string
    {
        return <<<'MD'
# 运营摘要

这里保存 Dify 只读摘要。每条摘要包含请求号、时间窗、区域、货类和来源说明。
MD;
    }

    public function compliance(): string
    {
        return <<<'MD'
# 禁止入库字段

不得写入手机号、证件号、订单号、运单号和未脱敏报价。货主线索电话留在业务系统。内容流水线不调用线索写入和消息推送。
MD;
    }

    public function policyIntro(): string
    {
        return <<<'MD'
# 政策合规

这里保存官方政策列表中的标题、原文链接和短摘录。每个工作日从中国政府网、国家发展改革委、交通运输部和广西交通运输厅的静态列表页写入。
MD;
    }

    public function marketIntro(): string
    {
        return <<<'MD'
# 行情数据

这里保存公路运价指数的公开稿摘录。中国物流与采购联合会的指数图表页由脚本渲染，定时任务改为抓取中国物流与采购网已经发布的运价指数稿。
MD;
    }

    public function corridorIntro(): string
    {
        return <<<'MD'
# 区域通道

这里保存广西壮族自治区人民政府和南宁市人民政府要闻的标题、链接和短摘录。北部湾港栏目页没有静态稿件链接，不进入定时抓取。
MD;
    }

    public function tradeIntro(): string
    {
        return <<<'MD'
# 外贸与东盟

海关总署新闻列表会拒绝没有浏览器会话的访问，中国—东盟博览会官网由脚本渲染。这两处不进入定时抓取，避免把空页面写进知识库。
MD;
    }
}
