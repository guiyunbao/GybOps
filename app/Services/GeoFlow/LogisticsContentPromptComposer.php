<?php

namespace App\Services\GeoFlow;

use App\Support\GeoFlow\ContentPromptContract;
use App\Support\GeoFlow\LogisticsContentPromptCatalog;

/**
 * 按物流场景和推广要点拼出可编辑的正文提示词初版。
 */
final class LogisticsContentPromptComposer
{
    public function __construct(
        private readonly LogisticsContentPromptCatalog $catalog,
        private readonly ContentPromptContract $contract,
    ) {}

    /**
     * @param  array{brand?: string, services?: string, audience?: string, notes?: string}  $brief
     * @return array{name: string, content: string}
     */
    public function compose(string $scenario, array $brief): array
    {
        $draft = $this->catalog->withSubject($scenario, $this->subject($brief));
        $this->contract->assert($draft['name'], $draft['content']);

        return $draft;
    }

    /**
     * @param  array{brand?: string, services?: string, audience?: string, notes?: string}  $brief
     */
    private function subject(array $brief): string
    {
        $lines = [];
        foreach ([
            'brand' => '公司或品牌',
            'services' => '核心业务',
            'audience' => '目标读者',
            'notes' => '补充要求',
        ] as $key => $label) {
            $value = $this->plain((string) ($brief[$key] ?? ''));
            if ($value !== '') {
                $lines[] = $label.'：'.$value;
            }
        }

        if ($lines === []) {
            return "【推广主体】\n未单独填写公司或品牌。文章中的企业名称、服务范围、资质、时效、运价和案例只能来自 {{Knowledge}}。知识不足时明确写出边界，不得编造。";
        }

        return "【推广主体】\n".implode("\n", $lines)."\n以上要点只作为写作方向。可以自然提及已填写的公司或品牌和业务，但运价、时效、资质、网点和案例仍只能来自 {{Knowledge}}。未出现在参考知识中的数字和承诺不要写。";
    }

    private function plain(string $value): string
    {
        $value = trim(str_replace(['{{', '}}'], '', $value));

        return trim((string) preg_replace("/[ \t]+/u", ' ', $value));
    }
}
