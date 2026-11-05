<?php
/**
 * Created by PhpStorm.
 * Author:ganxh
 * User: cp
 * Date: 2022/1/14
 * Time: 10:16
 */

?>
<html>
<head>
    <meta name="viewport" content="width=device-width">
    <script type="text/javascript" src="https://res.wx.qq.com/open/js/jweixin-1.4.0.js"></script>
    <script type="text/javascript" src="/js/uni.webview.1.5.2.js"></script>
    <script type="text/javascript" src="/js/vue.js"></script>
    <link rel="stylesheet" href="/css/muse-ui.css">
    <script src="/js/muse-ui.js"></script>
    <style>
        [v-cloak] {
            display: none;
        }
    </style>

</head>

<body>
<div id="app" v-cloak>
    <mu-dialog title="提示" width="360" :open.sync="showDialog">
        {{msg}}
        <mu-button slot="actions" flat color="primary" @click="closeDialog">我知道了</mu-button>
    </mu-dialog>
</div>
</body>


<script>
    MuseUI.theme.add('jxmall', {
        primary: ' #E22729',
        secondary: '#E22729',
        success: '#4caf50',
        warning: '#ffeb3b',
    }, 'light');
    MuseUI.theme.use('jxmall');
    document.addEventListener('UniAppJSBridgeReady', function () {
        var app = new Vue({
            el: '#app',
            data() {
                return {
                    showDialog: false,
                    msg: "<?=$msg?>",
                    code: "<?=$code?>",
                }
            },
            mounted() {
                if (this.code == 1) {
                    this.showDialog = true;
                }else{
                    uni.postMessage({
                        data:"<?=$access_token?>"
                    });
                    uni.navigateBack({
                        delta: 1
                    });
                }
            },
            methods:{
                closeDialog(){
                    this.showDialog = false;
                    uni.reLaunch({
                        url: '/pages/index/index'
                    });
                }
            }
        })
    });
</script>
</html>
