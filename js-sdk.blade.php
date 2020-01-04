<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>微信 js 分享接口</title>
    <meta name="viewpoint" content="initial-scale=1.0;width=device-width">
    <meta http-equiv="content" content="text/html;charset=utf-8">
    <script src="http://res.wx.qq.com/open/js/jweixin-1.6.0.js"></script>
</head>
<body>
<button id="share-friend">分享朋友</button>
<br>
<button id="share-alls">分享朋友圈</button>
<script>

  /**
   * 通过 config 接口注入权限验证配置
   */
  wx.config({
      debug: true, // 开启调试模式,调用的所有api的返回值会在客户端alert出来，若要查看传入的参数，可以在pc端打开，参数信息会通过log打出，仅在pc端时才会打印。
      appId: 'wxea34394d6d4b5acd', // 必填，调试测试，所以使用测试号 appId
      timestamp: '{{ $timestamp }}', // 必填，生成签名的时间戳
      nonceStr: '{{ $nonceStr }}', // 必填，生成签名的随机串
      signature: '{{ $signature }}',// 必填，签名
      jsApiList: [
          'updateAppMessageShareData',
          'updateTimelineShareData'
      ] // 必填，需要使用的JS接口列表
  });
  wx.ready(function(){
    /**
     * config信息验证后会执行ready方法，所有接口调用都必须在config接口获得结果之后，
     * config是一个客户端的异步操作，所以如果需要在页面加载时就调用相关接口，
     * 则须把相关接口放在ready函数中调用来确保正确执行。对于用户触发时才调用的接口，
     * 则可以直接调用，不需要放在ready函数中。
     */
    wx.onMenuShareTimeline({
      title: '老版分享朋友圈', // 分享标题
      link: 'http://ad.hwua.com/login', // 分享链接，该链接域名或路径必须与当前页面对应的公众号JS安全域名一致
      imgUrl: 'https://www.imooc.com/static/img/index/logo.png', // 分享图标
      success: function () {
        // 用户点击了分享后执行的回调函数
        alert('分享到朋友圈成功啦');
      },
      cancel: function () {
        alert('干啥啊，为啥要取消啊');
      }
    });

    wx.onMenuShareAppMessage({
      title: '老版分享给朋友', // 分享标题
      desc: '这是老版的分享给朋友的描述', // 分享描述
      link: 'http://ad.hwua.com/login', // 分享链接，该链接域名或路径必须与当前页面对应的公众号JS安全域名一致
      imgUrl: 'https://www.imooc.com/static/img/index/logo.png', // 分享图标
      type: 'link', // 分享类型,music、video或link，不填默认为link
      dataUrl: '', // 如果type是music或video，则要提供数据链接，默认为空
      success: function () {
        // 用户点击了分享后执行的回调函数
        alert('分享给朋友成功啦');
      },
      cancel: function () {
        alert('您已经取消了分享给朋友操作');
      }
    });

    // 新版分享给朋友圈
    // wx.updateTimelineShareData({
    //   title: '这是一个讲述神奇的 Kevin 的朋友圈消息，你准备好了吗', // 分享标题
    //   link: 'http://ad.hwua.com/login', // 分享链接，该链接域名或路径必须与当前页面对应的公众号JS安全域名一致
    //   imgUrl: 'https://www.imooc.com/static/img/index/logo.png', // 分享图标
    //   success: function () {
    //     // 设置成功
    //     alert('朋友圈分享成功啦啦啦啦啦！');
    //   }
    // });

    // 新版分享给朋友
    // wx.updateAppMessageShareData({
    //   title: '神奇的故事', // 分享标题
    //   desc: '这是一个神奇的故事', // 分享描述
    //   link: 'http://ad.hwua.com/login', // 分享链接，该链接域名或路径必须与当前页面对应的公众号JS安全域名一致
    //   imgUrl: 'https://www.imooc.com/static/img/index/logo.png', // 分享图标
    //   success: function () {
    //     // 设置成功
    //     alert('分享成功');
    //   }
    // });
  });
  wx.error(function(res){
    /**
     * config信息验证失败会执行error函数，如签名过期导致验证失败，
     * 具体错误信息可以打开config的debug模式查看，也可以在返回的res参数中查看，对于SPA可以在这里更新签名。
     */
  });
</script>
</body>
</html>