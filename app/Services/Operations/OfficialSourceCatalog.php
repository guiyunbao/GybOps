<?php

namespace App\Services\Operations;

/**
 * 官方信源清单。只有页面 HTML 中带有稿件链接的列表才会定时抓取。
 */
final class OfficialSourceCatalog
{
    public const CADENCE_WEEKDAY = 'weekday';

    public const CADENCE_WEEKLY = 'weekly';

    public const CADENCE_MANUAL = 'manual';

    /**
     * @return list<array{key:string,library:string,name:string,cadence:string,list_url:string,host:string,link_pattern:string,title_contains:?string,note:string}>
     */
    public function sources(): array
    {
        return [
            $this->html('gov_policy', OperationsKnowledgeCatalog::POLICY, '中国政府网政策', self::CADENCE_WEEKDAY, 'https://www.gov.cn/zhengce/index.htm', 'www.gov.cn', '#^/zhengce/(?:content/)?\d{6}/content_\d+\.htm$#'),
            $this->html('ndrc_notice', OperationsKnowledgeCatalog::POLICY, '国家发展改革委通知', self::CADENCE_WEEKDAY, 'https://www.ndrc.gov.cn/xxgk/zcfb/tz/', 'www.ndrc.gov.cn', '#^/xxgk/zcfb/tz/\d{6}/t\d+_\d+\.html$#'),
            $this->html('mot_news', OperationsKnowledgeCatalog::POLICY, '交通运输部要闻', self::CADENCE_WEEKDAY, 'https://www.mot.gov.cn/xinwen/jiaotongyaowen/index.html', 'www.mot.gov.cn', '#^/xinwen/jiaotongyaowen/\d{6}/t\d+_\d+\.html$#'),
            $this->html('gx_transport', OperationsKnowledgeCatalog::POLICY, '广西交通运输厅要闻', self::CADENCE_WEEKDAY, 'http://jtt.gxzf.gov.cn/xwdt/', 'jtt.gxzf.gov.cn', '#^/xwdt/(?:tpxw|jtxw)/t\d+\.shtml$#'),
            $this->html('wuliu_index', OperationsKnowledgeCatalog::MARKET, '中国物流与采购网运价指数', self::CADENCE_WEEKLY, 'https://www.chinawuliu.com.cn/', 'www.chinawuliu.com.cn', '#^/(?:lhhzq|xsyj)/\d{6}/\d{2}/\d+\.shtml$#', '运价'),
            $this->html('gx_gov', OperationsKnowledgeCatalog::CORRIDOR, '广西壮族自治区人民政府要闻', self::CADENCE_WEEKDAY, 'http://www.gxzf.gov.cn/gxyw/', 'www.gxzf.gov.cn', '#^/gxyw/t\d+\.shtml$#'),
            $this->html('nanning_gov', OperationsKnowledgeCatalog::CORRIDOR, '南宁市人民政府发布会', self::CADENCE_WEEKDAY, 'https://www.nanning.gov.cn/jdhy/xwfbh/', 'www.nanning.gov.cn', '#^/jdhy/xwfbh/.+/t\d+\.html$#'),
            $this->note('clic_chart', OperationsKnowledgeCatalog::MARKET, '中国物流与采购联合会指数页', self::CADENCE_WEEKLY, '指数图表页由脚本渲染，定时任务改为抓取中国物流与采购网已经发布的运价指数稿。'),
            $this->note('bbw_port', OperationsKnowledgeCatalog::CORRIDOR, '北部湾港', self::CADENCE_WEEKDAY, '栏目页没有静态稿件链接，不进入定时抓取。'),
            $this->note('customs_news', OperationsKnowledgeCatalog::TRADE, '海关总署新闻', self::CADENCE_WEEKLY, '列表页拒绝没有浏览器会话的访问，不进入定时抓取。'),
            $this->note('caexpo', OperationsKnowledgeCatalog::TRADE, '中国—东盟博览会', self::CADENCE_WEEKLY, '官网由脚本渲染，页面 HTML 没有可入库的稿件链接。'),
            $this->note('business_scripts', OperationsKnowledgeCatalog::INTERNAL_SCRIPTS, '业务话术与案例', self::CADENCE_MANUAL, '公司素材和官方案例由人工精选后入库。'),
        ];
    }

    /**
     * @return list<array{key:string,library:string,name:string,cadence:string,list_url:string,host:string,link_pattern:string,title_contains:?string,note:string}>
     */
    public function automatic(string $cadence): array
    {
        return array_values(array_filter(
            $this->sources(),
            fn (array $source): bool => $source['cadence'] === $cadence && $source['list_url'] !== '',
        ));
    }

    /**
     * @return array{key:string,library:string,name:string,cadence:string,list_url:string,host:string,link_pattern:string,title_contains:?string,note:string}
     */
    private function html(
        string $key,
        string $library,
        string $name,
        string $cadence,
        string $listUrl,
        string $host,
        string $pattern,
        ?string $titleContains = null,
    ): array {
        return [
            'key' => $key,
            'library' => $library,
            'name' => $name,
            'cadence' => $cadence,
            'list_url' => $listUrl,
            'host' => $host,
            'link_pattern' => $pattern,
            'title_contains' => $titleContains,
            'note' => '',
        ];
    }

    /**
     * @return array{key:string,library:string,name:string,cadence:string,list_url:string,host:string,link_pattern:string,title_contains:?string,note:string}
     */
    private function note(string $key, string $library, string $name, string $cadence, string $note): array
    {
        return [
            'key' => $key,
            'library' => $library,
            'name' => $name,
            'cadence' => $cadence,
            'list_url' => '',
            'host' => '',
            'link_pattern' => '',
            'title_contains' => null,
            'note' => $note,
        ];
    }
}
