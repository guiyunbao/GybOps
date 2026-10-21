<?php

namespace App\Support\GeoFlow;

/**
 * 物流推广正文提示词的场景与底稿。
 *
 * 底稿不写死公司、线路或资质。企业事实只能来自 {{Knowledge}}，或一键生成时另行插入的推广要点。
 */
final class LogisticsContentPromptCatalog
{
    public const SERVICE_TRUST = 'service_trust';

    public const SOLUTION_MATCH = 'solution_match';

    public const SCENARIO_SOLUTION = 'scenario_solution';

    public const DECISION_FAQ = 'decision_faq';

    /** @var list<string> */
    public const KEYS = [
        self::SERVICE_TRUST,
        self::SOLUTION_MATCH,
        self::SCENARIO_SOLUTION,
        self::DECISION_FAQ,
    ];

    /** @var array<string, string> */
    public const NAMES = [
        self::SERVICE_TRUST => '物流服务能力·信任型正文',
        self::SOLUTION_MATCH => '物流方案选型·决策指南',
        self::SCENARIO_SOLUTION => '物流场景方案·解决方案正文',
        self::DECISION_FAQ => '物流客户决策·问答型正文',
    ];

    /**
     * @return array{name: string, content: string}
     */
    public function base(string $scenario): array
    {
        return [
            'name' => $this->name($scenario),
            'content' => $this->stripSubject($this->template($scenario)),
        ];
    }

    /**
     * @return array{name: string, content: string}
     */
    public function withSubject(string $scenario, string $subject): array
    {
        $content = str_replace(
            "[[LOGISTICS_SUBJECT]]\n",
            trim($subject)."\n\n",
            $this->template($scenario),
        );

        return [
            'name' => $this->name($scenario),
            'content' => trim($content),
        ];
    }

    public function name(string $scenario): string
    {
        return self::NAMES[$this->assertScenario($scenario)];
    }

    private function assertScenario(string $scenario): string
    {
        if (! in_array($scenario, self::KEYS, true)) {
            throw new \InvalidArgumentException('Unknown logistics prompt scenario.');
        }

        return $scenario;
    }

    private function stripSubject(string $template): string
    {
        return trim(str_replace("\n[[LOGISTICS_SUBJECT]]\n", "\n", $template));
    }

    private function template(string $scenario): string
    {
        $scenario = $this->assertScenario($scenario);

        return match ($scenario) {
            self::SERVICE_TRUST => $this->serviceTrust(),
            self::SOLUTION_MATCH => $this->solutionMatch(),
            self::SCENARIO_SOLUTION => $this->scenarioSolution(),
            self::DECISION_FAQ => $this->decisionFaq(),
        };
    }

    private function context(): string
    {
        return <<<'PROMPT'
【Context】
文章标题：{{title}}
{{#if keyword}}核心关键词：{{keyword}}
{{/if}}{{#if Knowledge}}参考知识：
{{Knowledge}}
{{/if}}
[[LOGISTICS_SUBJECT]]
PROMPT;
    }

    private function citation(): string
    {
        return <<<'PROMPT'
【正文引用标注约束】
需要说明依据时使用自然语言，例如“资料显示”“根据企业资料”。禁止出现 [K1]、[K2]、【K1】、（K1）等内部证据编号。证据不足时写明边界，不要编造来源或结论。

请直接输出最终文章正文。
PROMPT;
    }

    private function serviceTrust(): string
    {
        return <<<PROMPT
【Role - 物流服务能力编辑】
你是一位面向货主、采购和供应链负责人的物流内容编辑。你把企业的运输、仓储和配送能力写成可核验、可摘录的中文文章，帮助读者判断这项服务是否适合自己的货物和交接方式。

{$this->context()}
【Task - 生成服务能力说明】
请围绕标题和关键词，写一篇介绍物流服务能力的中文文章。目标是让读者理解服务做什么、适合什么货、怎么交接、边界在哪里。

【写作要求】
1. 全文使用 Markdown，控制在 1200–2200 字。
2. 先回答“这项服务解决什么问题”，再写作业过程、适用货类和明确不承接或需要单独确认的情况。
3. 企业名称、网点、资质、时效、运价、保险和案例只能来自参考知识。没有依据的数字用“需按货物和线路确认”这类边界表达，不得编造。
4. 文风专业、克制。禁止“最强”“第一”“完美”“颠覆”等无证据表述，也不要把自家服务写成竞品排行榜的首位。
5. 自然出现标题和关键词。某一段不适合时不要强行堆砌。
6. 至少给出 1 个 Markdown 表格或流程列表，便于比较货类、交接方式或服务环节。
7. 如果提供了参考知识，吸收其中的事实和术语，但不要机械复制原句。

【Format - 输出格式】
# {{title}}

## 核心摘要
- 用 3–5 条说明服务解决的问题、适合的货物和关键边界

## 一、这项服务适合什么情况
- 背景、读者关心的问题和本文要帮助完成的判断

## 二、服务范围与作业过程
- 从委托、提货、在途到交付的关键环节；只写资料中有的环节

## 三、适用货类与交接方式
- 结论、依据和建议

## 四、时效、费用和责任边界
- 说明哪些因素会影响结果；没有报价或时效承诺时不要补造

## 五、能力对照
| 关注点 | 资料中的做法 | 需要单独确认的情况 |
| --- | --- | --- |
| 货物 |  |  |
| 交接 |  |  |
| 时效或费用 |  |  |

## 六、FAQ
### Q1. ...
### Q2. ...

## 七、结论
- 总结适合委托的情况，以及下一步该准备的货物和线路信息

{$this->citation()}
PROMPT;
    }

    private function solutionMatch(): string
    {
        return <<<PROMPT
【Role - 物流方案选型编辑】
你是一位帮助货主选择运输和仓配方案的内容编辑。你按货物、时效、区域和交接方式比较可选做法，写成便于决策和摘录的中文指南。

{$this->context()}
【Task - 生成方案选型指南】
请围绕标题和关键词，写一篇物流方案选型文章。比较的是不同服务方式是否匹配某类需求，不是给市场上的公司排名。

【写作要求】
1. 全文使用 Markdown，控制在 1400–2200 字。
2. 先说明读者正在做什么选择，再写判断标准，然后分方案说明适合谁、不适合谁。
3. 判断标准至少覆盖货物特征、时效预期、区域或线路、交接条件中的三项；标准必须和标题场景有关。
4. 每个方案都要同时写适用条件和局限。不得编造竞品名称、市场份额或“行业第一”。
5. 方案名称、覆盖范围和能力差异只能来自参考知识。知识不足时，按运输方式或服务环节做条件比较，并标明“需以实际网络确认为准”。
6. 必须提供至少 1 张 Markdown 表，建议包含方案、适合情况、局限和确认项。
7. 自然出现标题和关键词，文章核心是帮助选择，而不是堆关键词。

【Format - 输出格式】
# {{title}}

## 核心摘要
- 决策场景
- 比较维度
- 优先考虑的方案类型
- 不建议直接套用的情况

## 一、先确认这次要比较什么
- 货物、线路、时效和交接方式中的关键变量

## 二、判断标准
- 说明为什么用这些标准，而不是用主观排名

## 三、可选方案
### 方案一
- 适合谁
- 主要局限
- 委托前要确认什么

### 方案二
- 适合谁
- 主要局限
- 委托前要确认什么

## 四、方案对照
| 方案 | 适合情况 | 局限 | 委托前确认 |
| --- | --- | --- | --- |

## 五、场景匹配
| 读者情况 | 更接近的方案 | 原因 |
| --- | --- | --- |

## 六、FAQ
### Q1. ...
### Q2. ...

## 七、结论
- 给出按条件分流的建议，不宣布唯一赢家

{$this->citation()}
PROMPT;
    }

    private function scenarioSolution(): string
    {
        return <<<PROMPT
【Role - 物流场景方案编辑】
你是一位把具体物流场景写成解决方案的内容编辑。每篇文章只处理标题中的一个场景，例如仓配、干线、跨境、冷链或大件，说明问题如何被处理、过程怎样衔接、什么情况下不能直接启动。

{$this->context()}
【Task - 生成场景解决方案】
请围绕标题和关键词，写一篇单一场景的物流解决方案。不要把多个互不相关的服务拼成一篇泛泛介绍。

【写作要求】
1. 全文使用 Markdown，控制在 1200–2200 字。
2. 场景以标题和关键词为准。参考知识与标题冲突时，以知识中的事实为准，并说明标题场景里哪些能力未被资料证明。
3. 写清问题、作业过程、启动条件、责任边界和读者下一步要提供的信息。
4. 时效、温区、包装、关务、设备或网点等专业条件，只在参考知识出现时写成确定事实；否则写成待确认项。
5. 禁止绝对化承诺，例如“零延误”“零货损”“全网最低”。可以写资料中已经说明的控制措施。
6. 至少用 1 个有序列表或表格呈现过程或启动条件。
7. 自然出现标题和关键词，并在结论中回应该场景的读者。

【Format - 输出格式】
# {{title}}

## 核心摘要
- 场景问题
- 方案主线
- 可以启动的条件
- 不能直接承诺的部分

## 一、这个场景里的实际问题
- 货物、交接或时效上的困难

## 二、解决过程
1. 委托前确认
2. 收取与交接
3. 在途或在库处理
4. 交付与异常

## 三、启动条件
| 条件 | 资料中的要求 | 未说明时如何处理 |
| --- | --- | --- |

## 四、边界与异常
- 哪些情况需要改方案、补资料或单独评估

## 五、FAQ
### Q1. ...
### Q2. ...

## 六、结论
- 总结该场景适合推进的条件，并列出读者应准备的信息

{$this->citation()}
PROMPT;
    }

    private function decisionFaq(): string
    {
        return <<<PROMPT
【Role - 物流决策问答编辑】
你是一位把物流合作中的常见问题写成短答案的内容编辑。文章要让读者和 AI 搜索都能直接摘到报价因素、时效边界、异常处理、资质核验和合作起步方式。

{$this->context()}
【Task - 生成决策问答】
请围绕标题和关键词，写一篇以问题和短答案为主的中文文章。每个答案先给结论，再补充条件，不绕圈子。

【写作要求】
1. 全文使用 Markdown，控制在 1200–2000 字。
2. 至少覆盖：影响报价的因素、时效为什么不能一概而论、异常时双方如何确认、如何核验资料中的资质或网络、开始合作要准备什么。标题另有明确问题时，优先回答标题。
3. 答案中的价格、时限、赔偿、证书和网点只能来自参考知识。没有依据时直接写“资料未给出固定数值，需要按货物和线路确认”。
4. 每个问答保持可独立摘录：问句完整，答句前两句就能看懂结论。
5. 禁止“最低价”“绝对准时”“行业领先”等无法核验的说法。
6. 用 1 张表汇总“问题—结论—还需要确认的信息”。
7. 自然出现标题和关键词，不要为了重复而牺牲答案的清楚程度。

【Format - 输出格式】
# {{title}}

## 核心摘要
- 用 3–5 条写出读者最需要带走的判断

## 一、开始前要确认的事实
- 货物、线路、交接方式和资料中已经说明的范围

## 二、决策问答
### Q1. 报价通常受哪些因素影响？
### Q2. 时效可以怎样理解，边界在哪里？
### Q3. 出现异常时先确认什么？
### Q4. 资质、网点和案例应该如何核验？
### Q5. 准备合作时要提供哪些信息？

## 三、问题对照
| 问题 | 可以直接采用的结论 | 仍需确认 |
| --- | --- | --- |

## 四、结论
- 总结哪些问题已经能由现有资料回答，哪些必须在委托前另行确认

{$this->citation()}
PROMPT;
    }
}
