<?php

Route::group(['prefix' => 'wx'], function() {
    Route::any('server', 'WxController@server');
    Route::any('curl', 'WxController@httpCurl');
    Route::get('global/access-token', 'WxController@getWxGlobalAccessToken');
    Route::get('ip/list', 'WxController@getWxServerIpList');
    Route::get('user/info', 'WxController@getWxUserInfo');
    Route::get('auth/silent', 'WxController@getAuthSilent'); // 静默授权回调
    Route::get('auth/show', 'WxController@getAuthShow'); // 显式授权回调
    Route::get('menu/create', 'WxController@getMenuCreate'); // 创建自定义菜单
    Route::get('group-message/send', 'WxController@groupMessageSend'); // 群发消息接口调试
    Route::get('tpl-message/send', 'WxController@sendTplMsg'); // 群发消息接口调试
    Route::get('js-sdk', 'WxController@wxJsSdk'); // 微信 jssdk 使用
    Route::get('temp/qrcode', 'WxController@tempQrCode'); // 临时二维码
    Route::get('everlasting/qrcode', 'WxController@everlastingQrCode'); // 临时二维码
    Route::get('test', 'WxController@test'); // 测试基础字符
});