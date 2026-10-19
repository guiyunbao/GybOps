<?php

namespace App\Services\Operations;

use App\Models\OperationsDifyConnection;
use App\Support\GeoFlow\ApiKeyCrypto;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * 调用 Dify 对话接口生成只读运营摘要。
 */
final class DifyOperationsBriefClient
{
    public function __construct(private readonly ApiKeyCrypto $crypto) {}

    /**
     * @param  array{region:string,cargo_type:string,window_start:string,window_end:string,request_id:string}  $input
     */
    public function summarize(OperationsDifyConnection $connection, array $input): string
    {
        $apiKey = $this->crypto->decrypt((string) $connection->api_key);
        if ($apiKey === '') {
            throw new RuntimeException('operations_dify_key_unreadable');
        }

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout(110)
            ->post(rtrim($connection->base_url, '/').'/v1/chat-messages', [
                'inputs' => [
                    'region' => $input['region'],
                    'cargo_type' => $input['cargo_type'],
                    'window_start' => $input['window_start'],
                    'window_end' => $input['window_end'],
                ],
                'query' => $this->query($input),
                'response_mode' => 'blocking',
                'user' => 'geoflow-operations-'.$input['request_id'],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('operations_dify_http_'.$response->status());
        }

        $answer = trim((string) $response->json('answer'));
        if ($answer === '') {
            throw new RuntimeException('operations_dify_empty_answer');
        }

        return $answer;
    }

    /**
     * @param  array{region:string,cargo_type:string,window_start:string,window_end:string,request_id:string}  $input
     */
    public function query(array $input): string
    {
        return implode("\n", [
            '请根据已有只读业务工具，汇总运营摘要。',
            '区域：'.$input['region'],
            '货类：'.$input['cargo_type'],
            '时间窗：'.$input['window_start'].' 至 '.$input['window_end'],
            '只输出热门线路、货类、运价区间和线索行业分布。',
            '每条数字标注来源为业务系统聚合。',
            '不要输出联系电话、证件号、订单号、运单号。',
            '不要写入货主线索，不要发送短信或应用推送。',
            '请求号：'.$input['request_id'],
        ]);
    }
}
