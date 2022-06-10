<?php
namespace App\Http\Controllers\Pay;


use App\Exceptions\RuleValidationException;
use App\Http\Controllers\PayController;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;

class XorPayController extends PayController
{

    public function gateway(string $payway, string $orderSN)
    {
        try {
            // 加载网关
            $this->loadGateWay($orderSN, $payway);
            // 构造请求参数
            switch ($payway) {
                case 'xorwxx': $pay_type = 'native'; break;
                case 'xorali': $pay_type = 'alipay'; break;
            }
            $parameter = array(
                "name" => $this->order->title,                  // 商品名称
                "pay_type" => $pay_type,                        // 支付类型
                "price" => (float)$this->order->actual_price,   // 原价
                "order_id" => $this->order->order_sn,           // 商户订单号
                'notify_url' => url($this->payGateway->pay_handleroute . '/notify_url'),
                // 'return_url' => url('xorpay-return', ['order_id' => $this->order->order_sn]),
            );
            // 构造签名
            $sign = '';
            foreach ($parameter as $key => $val) {
                if ($key == 'return_url') continue;
                $sign .= $val;
            }
            $parameter['sign'] = md5($sign . $this->payGateway->merchant_key);
            error_log(print_r($parameter, TRUE));
            // 请求二维码
            $payapi = 'https://xorpay.com/api/pay/' . $this->payGateway->merchant_id;
            $client = new Client();
            $resp = $client->post($payapi, ['form_params' => $parameter]);
            $data = json_decode($resp->getBody()->getContents(), true);
            if (!isset($data['status']) || $data['status'] != 'ok') {
                return $this->err(__('dujiaoka.prompt.abnormal_payment_channel') . $data['status']);
            }
            // 渲染二维码
            $context = array();
            $context['payname'] = $this->payGateway->pay_name;
            $context['actual_price'] = (float)$this->order->actual_price;
            $context['qr_code'] = $data['info']['qr'];
            $context['jump_payuri'] = $data['info']['qr'];
            $context['orderid'] = $this->order->order_sn;
            return $this->render('static_pages/qrpay', $context, __('dujiaoka.scan_qrcode_to_pay'));
        } catch (RuleValidationException $exception) {
        } catch (GuzzleException $exception) {
            return $this->err($exception->getMessage());
        }
    }


    public function notifyUrl(Request $request)
    {
        $data = $request->all();
        $order = $this->orderService->detailOrderSN($data['order_id']);
        if (!$order) {
            return 'fail';
        }
        $payGateway = $this->payService->detail($order->pay_id);
        if (!$payGateway) {
            return 'fail';
        }

        $aoid = $data['aoid'];
        $order_id = $data['order_id'];
        $pay_price = $data['pay_price'];
        $pay_time = $data['pay_time'];

        // 签名校验失败
        $sign = md5($aoid . $order_id . $pay_price . $pay_time . $payGateway->merchant_key);
        if ($sign != $data['sign']) {
            return 'fail';
        }

        // 回调成功
        $this->orderProcessService->completedOrder($order_id, $pay_price, $aoid);
        return 'success';
    }


    public function returnUrl(Request $request)
    {
        $oid = $request->get('order_id');
        // 异步通知还没到就跳转了，所以这里休眠2秒
        sleep(2);
        return redirect(url('detail-order-sn', ['orderSN' => $oid]));
    }

}


