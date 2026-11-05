<?php
/**
 * @var $mall_id
 * @var $pay_order_id
 * @var $openid
 */
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <title>收银台</title>
    <script src="https://cdn.bootcdn.net/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <style>
        body {
            padding: 50px 20px;
            text-align: center;
            font-family: sans-serif;
        }

        .loading {
            font-size: 16px;
            color: #666;
        }

        .error {
            color: #f56c6c;
            margin-top: 20px;
        }

        .success {
            color: #67c23a;
            font-size: 18px;
            font-weight: bold;
        }
    </style>
</head>
<body>

<div id="status" class="loading">正在获取支付信息...</div>

<script>
    var mallId = "<?= $mall_id ?>";
    var pay_order_id = "<?= $pay_order_id ?>";
    var openid = "<?= $openid ?>";

    // 页面加载完毕，立即请求支付参数
    $(function () {
        getPayParams();
    });

    function getPayParams() {
        $.ajax({
            url: '/index.php?r=/wechat/wx-work-pay/get-pay-params', // 调用上面控制器里的 Action
            type: 'POST',
            dataType: 'json',
            data: {
                mall_id: mallId,
                pay_order_id: pay_order_id,
                openid: openid,
                _csrf: '<?= Yii::$app->request->csrfToken ?>' // 如果没关CSRF这里要传
            },
            success: function (res) {
                if (res.code === 0) {
                    // 拿到参数，唤起微信支付
                    callWechatPay(res.data);
                } else if (res.data && res.data.is_pay == 1) {
                    $('#status').html('<div class="success">该订单已支付</div>');
                } else {
                    $('#status').html('<div class="error">' + res.msg + '</div>');
                }
            },
            error: function () {
                $('#status').html('<div class="error">网络请求失败，请刷新重试</div>');
            }
        });
    }

    function callWechatPay(payParams) {
        $('#status').text('正在唤起微信支付...');

        if (typeof WeixinJSBridge == "undefined") {
            if (document.addEventListener) {
                document.addEventListener('WeixinJSBridgeReady', function () {
                    onBridgeReady(payParams);
                }, false);
            } else if (document.attachEvent) {
                document.attachEvent('WeixinJSBridgeReady', function () {
                    onBridgeReady(payParams);
                });
                document.attachEvent('onWeixinJSBridgeReady', function () {
                    onBridgeReady(payParams);
                });
            }
        } else {
            onBridgeReady(payParams);
        }
    }

    function onBridgeReady(params) {
        // 注意：有些支付网关返回的参数结构可能包了一层，请根据实际情况调整
        // 比如 params.appId 或者 params.data.appId

        WeixinJSBridge.invoke(
            'getBrandWCPayRequest', {
                "appId": params.appId,     // 公众号ID
                "timeStamp": params.timeStamp, // 时间戳
                "nonceStr": params.nonceStr, // 随机串
                "package": params.package,     // 订单详情扩展字符串
                "signType": params.signType, // 签名方式
                "paySign": params.paySign // 签名
            },
            function (res) {
                if (res.err_msg == "get_brand_wcpay_request:ok") {
                    $('#status').html('<div class="success">支付成功！<br>请关闭此窗口返回客服会话</div>');
                } else if (res.err_msg == "get_brand_wcpay_request:cancel") {
                    $('#status').text('您取消了支付');
                } else {
                    $('#status').html('<div class="error">支付失败: ' + res.err_msg + '</div>');
                }
            }
        );
    }
</script>
</body>
</html>