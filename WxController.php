<?php

namespace App\Http\Controllers;

use App\Services\WxService;
use Illuminate\Support\Facades\Log;

class WxController extends Controller
{
    /**
     * 微信公众账号校验第三方 url 的合法性
     *  1.编写好当前脚本后，在微信公众平台配置好 token 和本脚本公网 url 地址以及其他相关配置（消息加解密方式、EncodingAESKey）
     *  2.点击提交，微信服务器会对当前脚本进行验证，验证通过则提交成功。否则显示提交失败以及错误信息。
     *
     * 注意：
     *  当公众平台初次提交 url、token 等配置或更改配置提交时，微信服务器此时会用 get 方式请求配置的 url 地址来校验 url 的合法性，
     *  此时传递过来有四个参数（timestamp、nonce、signature 和 echostr），而当有事件推送的时候，请求的 get 参数是没有 echostr 的，
     *  这个可以用来区分对当前接口是否是校验合法性或者其他目的的请求
     */
    public function server()
    {
        if (isset($_GET['echostr'])) {
            $echoStr = $_GET['echostr'];
            if ($this->valid($echoStr)) {
                echo $echoStr;
                exit;
            }
        } else { // 不存在 $echostr 则表示是其他目的请求
            $this->responseMsg();
        }
    }

    /**
     * 校验服务器 url 合法性
     *
     * @param $echoStr
     * @return bool
     */
    private function valid($echoStr)
    {
        // 获取请求参数
        $timestamp = $_GET['timestamp'];
        $nonce     = $_GET['nonce'];
        $signature = $_GET['signature'];
        $token     = 'loedan'; // 需要与公众平台设置的 token 一致

        // 1.将 timestamp，token，nonce 三个参数按字典序排序
        $arr = [$timestamp, $nonce, $token];
        sort($arr, SORT_STRING);

        // 2.将排序后的三个参数拼接成字符串之后用 sha1 加密
        $str = sha1(implode($arr));

        // 3.将加密后的字符串与 signature 进行对比，判断该请求是否来自微信
        if ($str == $signature && $echoStr) { // 存在 $echostr 则表示是校验 url 的合法性
            return true;
        } else {
            return false;
        }
    }

    /**
     * 接收事件推送并回复
     */
    public function responseMsg()
    {
        /**
         * 微信的事件推送发送的 post 数据是 xml 格式的
         */
        // 获取请求中的 xml 数据
        // post 请求 xml 形式数据存储在 $GLOBALS['HTTP_RAW_POST_DATA'] 中
        $postXml = isset($GLOBALS["HTTP_RAW_POST_DATA"]) ? $GLOBALS["HTTP_RAW_POST_DATA"] : file_get_contents("php://input");
        libxml_disable_entity_loader(true);

        if (!empty($postXml)) {
            // 解析 xml 数据成对象
            $postObj = simplexml_load_string($postXml, 'SimpleXMLElement', LIBXML_NOCDATA);

            // 消息类型
            $postMsgType = trim(strtolower($postObj->MsgType));
            switch ($postMsgType) {
                case 'event': $this->handleEvent($postObj); break; // 消息类型为 event 时
                case 'text': $this->handleText($postObj); break; // 消息类型为单文本时
                default: echo ''; exit; break;
            }
        } else {
            echo '';
            exit;
        }
    }

    /**
     * 处理 text 单文本类型事件推送
     * @param $postObj
     */
    private function handleText($postObj)
    {
        // 根据接收到的信息作出不同的回复
        $postContent = trim($postObj->Content);
        if ($postContent == 'news') { // 收到 news，则返回图文消息
            $data = [
                ['title' => 'Imooc', 'description' => 'this is imooc', 'picUrl' => 'https://www.imooc.com/static/img/index/logo.png', 'url' => 'https://www.imooc.com/'],
                ['title' => '网易云课堂', 'description' => 'this is 网易云课堂', 'picUrl' => 'https://edu-image.nosdn.127.net/51373455cc3e4a96a802e89387cb868c.png?imageView&quality=100', 'url' => 'https://study.163.com/'],
                ['title' => '百度传课', 'description' => 'this is baidu', 'picUrl' => 'http://ckres.baidu.com/sites/www/v2/images/user/logo_t.png', 'url' => 'https://chuanke.baidu.com/'],
                ['title' => '腾讯课堂', 'description' => 'this is tencent', 'picUrl' => 'http://8.url.cn/edu/edu_modules/edu-ui/img/bg/logo192-3x.e89120ba.png', 'url' => 'https://ke.qq.com/'],
            ];
            $wxService = new WxService();
            echo $wxService->responseMsg($postObj, 'news', $data);
        } else {
            switch ($postContent) {
                case 'kevin':
                    $data = "this is kevin response!";
                    $wxService = new WxService();
                    echo $wxService->responseMsg($postObj, 'text', $data);
                    break;
                case 'link': // 回复一个链接，可在微信浏览器中跳转
                    $data = "<a href='http://www.imooc.com'>Imooc</a>";
                    $wxService = new WxService();
                    echo $wxService->responseMsg($postObj, 'text', $data);
                    break;
                default:
                    $data = "this is response from Loedan!";
                    $wxService = new WxService();
                    echo $wxService->responseMsg($postObj, 'text', $data);
                    break;
            }
        }
    }

    /**
     * 处理 event 类型事件推送
     * @param $postObj
     */
    private function handleEvent($postObj)
    {
        $postEvent = trim(strtolower($postObj->Event));
        switch ($postEvent) {
            case 'subscribe': $this->handleSubscribeEvent($postObj); break;// subscribe 用户关注公众号事件
            case 'click': $this->handleClickEvent($postObj); break; // click 为自定义菜单点击事件
            case 'scan': $this->handleScanEvent($postObj); break; // scan 为扫描带场景值的二维码事件
        }
    }

    /**
     * 处理 event 类型下 scan 的事件推送
     * @param $postObj
     */
    private function handleScanEvent($postObj)
    {
        switch ($postObj->EventKey) { // 对二维码的不同场景值做不同处理
            case '1':
                $data = "已关注，临时二维码!";
                $wxService = new WxService();
                echo $wxService->responseMsg($postObj, 'text', $data);
                break;
            case '2':
                $data = "已关注，永久二维码!";
                $wxService = new WxService();
                echo $wxService->responseMsg($postObj, 'text', $data);
                break;
        }
    }

    /**
     * 处理 event 类型下的 subscribe 的事件推送
     * @param $postObj
     */
    private function handleSubscribeEvent($postObj)
    {
        // 有 EventKey 和 Ticket 表示是未关注用户扫描了带参二维码后进行了关注的事件推送
        // property_exists(); 检查类或对象中是否存在某个属性
        if (property_exists($postObj, 'EventKey') && property_exists($postObj, 'Ticket')) {
            switch ($postObj->EventKey) { // 对二维码的不同场景值做不同处理
                case 'qrscene_1': // 未关注用户扫带场景值二维码并关注的接到的 EventKey 为 qrscene_ 开头，后面的是自己设置的场景值
                    $data = "未关注，临时二维码!";
                    $wxService = new WxService();
                    echo $wxService->responseMsg($postObj, 'text', $data);
                    break;
                case 'qrscene_2':
                    $data = "未关注，永久二维码!";
                    $wxService = new WxService();
                    echo $wxService->responseMsg($postObj, 'text', $data);
                    break;
            }
        } else { // 正常关注流程关注
            $data = "欢迎关注 Loedan 个人公众号!";
            $wxService = new WxService();
            /**
             * 测试属性不存在抛出异常
             *
             * $postObj = json_decode($postObj, true);
             * unset($postObj['ToUserName']);
             * json_encode($postObj);
             */
            echo $wxService->responseMsg($postObj, 'text', $data);
        }
    }

    /**
     * 处理 event 类型下 click 的事件推送
     * @param $postObj
     */
    private function handleClickEvent($postObj)
    {
        $postEventKey = trim(strtolower($postObj->EventKey));
        switch ($postEventKey) {
            case 'about_me':
                $data = '静若瘫痪，动若癫痫的 kevin 爸爸！';
                $wxService = new WxService();
                echo $wxService->responseMsg($postObj, 'text', $data);
                break;
            default:
                echo '';
                exit;
                break;
        }
    }

    /**
     * 获取全局 access_token
     */
    public function getWxGlobalAccessToken()
    {
        $wxService = new WxService();
        $res = $wxService->getWxGlobalAccessToken();
        var_dump($res);
    }

    /**
     * 获取微信服务器 ip 地址集
     *
     */
    public function getWxServerIpList()
    {
        $wxService = new WxService();
        $accessToken = $wxService->getWxGlobalAccessToken();
        $res = $wxService->getWxServerIpList($accessToken);
        var_dump($res);
    }

    /**
     * 静默授权只能获取 openid
     * 显式授权可以获取到用户详细信息（昵称，头像，openid...）
     */
    public function getWxUserInfo()
    {
        $scope = $_GET['scope'];
        try {
            if (!in_array($scope, ['snsapi_base', 'snsapi_userinfo'])) {
                throw new \Exception('授权模式错误');
            }
        } catch (\Exception $e) {
            echo $e->getMessage();
            exit;
        }
        if ($scope == 'snsapi_base') {
            $redirectUri = env('APP_URL').'/wx/auth/silent';
        } else {
            $redirectUri = env('APP_URL').'/wx/auth/show';
        }
        $wxService = new WxService();
        $wxService->getOauthCode($scope, $redirectUri);
    }

    /**
     * 静默授权回调
     * 订阅号没有接口权限
     */
    public function getAuthSilent()
    {
        $code = $_GET['code'];
        $wxService = new WxService();
        $res = $wxService->getAccessTokenByCode($code);
        print_r($res);
    }

    /**
     * 显式授权回调
     * 订阅号没有接口权限
     */
    public function getAuthShow()
    {
        $code = $_GET['code'];
        $wxService = new WxService();
        $res = $wxService->getAccessTokenByCode($code);
        try {
            if (!isset($res['access_token']) || !isset($res['openid'])) {
                throw new \Exception('微信 access_token 异常');
            }
            $userInfo = $wxService->getUserInfoByAccessToken($res['access_token'], $res['openid']);
            print_r($userInfo);
        } catch (\Exception $e) {
            Log::info("显示授权异常：" . $e->getMessage());
        }
    }

    /**
     * 创建自定义菜单
     */
    public function getMenuCreate()
    {
        $wxService = new WxService();
        $accessToken = $wxService->getWxGlobalAccessToken();
        $menu = [
            'button' => [
                // 第一个一级菜单
                ['type' => 'click', 'name' => 'AboutMe', 'key' => 'about_me'],
                // 第二个一级菜单
                ['name' => '学习网站', 'sub_button' => [
                    // 第一个二级菜单
                    ['type' => 'view', 'name' => '慕课网', 'url' => 'https://www.imooc.com'],
                    // 第二个二级菜单
                    ['type' => 'view', 'name' => '网易云课堂', 'url' => 'http://study.163.com'],
                ]],
                // 第三个一级菜单
                //[],
            ]
        ];
        $res = $wxService->createMenu($accessToken, $menu);
        if ($res['errcode'] != 0) {
            Log::info('创建菜单失败');
        } else {
            Log::info('创建菜单成功');
        }
    }

    /**
     * 群发消息接口调试
     */
    public function groupMessageSend()
    {
        /**
         * 测试号等同于认证后的服务号，群发信息用户每月只能接收到 4 条，为了便于调试，应该使用预览接口
         */
        // 单文本格式内容发送
        $data = [
            // 测试号测试，只是用关注了一个测试号的微信用户
            'touser' => 'oVxBJv1phVdkGQiDTImq_PyDLq6k',
            'text' => ['content' => '封装好 sdk 后的群发测试',],
            'msgtype' => 'text'
        ];
        $wxService = new WxService();
        // 1.获取 access_token
        $accessToken = $wxService->getWxGlobalAccessToken();
        try {
            $res = $wxService->groupMsgSend($accessToken, 'text', $data);
            print_r($res);
        } catch (\Exception $e) {
            Log::info('群发消息失败：' . $e->getMessage());
        }
    }

    /**
     * 发送模板消息
     */
    public function sendTplMsg()
    {
        $wxService = new WxService();
        $accessToken = $wxService->getWxGlobalAccessToken();
        $data = [
            'touser' => 'oVxBJv8MhL8rI0OhoTBIrlR8RPvs', // openid 调试用测试号 openid
            'template_id' => 'zZXN-32gQ_cQB4fJJAfPh7QVUvc0FQKFaCXL_7rqmZc', // 调试用测试号模板ID
            'url' => 'http://www.imooc.com',
            'data' => [
                'name' => ['value' => 'Kevin', 'color' => '#173177'],
                'birthday' => ['value' => '1819-12-16', 'color' => '#173177'],
            ]
        ];
        $res = $wxService->sendTemplateMsg($accessToken, $data);
        print_r($res);
    }

    /**
     * 微信 jssdk
     */
    public function wxJsSdk()
    {
        $wxService = new WxService();
        $accessToken = $wxService->getWxGlobalAccessToken();
        // 获取 jsapi_ticket
        $jsapiTicket = $wxService->getJsApiTicket($accessToken);
        // 生成签名
        $timestamp = time();
        $nonceStr = WxService::getNonceStr(16);
        $url = $_SERVER['APP_URL'] . $_SERVER['REQUEST_URI']; // 当前页面的 url
        $str = 'jsapi_ticket='.$jsapiTicket.'&noncestr='.$nonceStr.'&timestamp='.$timestamp.'&url='.$url;
        $signature = sha1($str);

        return view('wx/js-sdk', compact('timestamp', 'nonceStr', 'signature'));
    }

    /**
     * 临时二维码
     */
    public function tempQrCode()
    {
        $wxService = new WxService();
        $accessToken = $wxService->getWxGlobalAccessToken();
        // 获取创建二维码的 ticket
        $data = [
            'expire_seconds' => 604800,
            'action_name' => 'QR_SCENE',
            'action_info' => [
                'scene' => [
                    'scene_id' => 1
                ]
            ]
        ]; // 也可以使用 str 类型的二维码场景值，具体看文档
        $ticket = $wxService->getTempQrCodeTicket($accessToken, $data);
        // 得到二维码
        $src = 'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket='.urlencode($ticket);
        echo "<img src='".$src."' />";
    }

    /**
     * 永久二维码
     */
    public function everlastingQrCode()
    {
        $wxService = new WxService();
        $accessToken = $wxService->getWxGlobalAccessToken();
        // 获取创建二维码的 ticket
        $data = [
            'action_name' => 'QR_LIMIT_SCENE',
            'action_info' => [
                'scene' => [
                    'scene_id' => 2
                ]
            ]
        ]; // 也可以使用 str 类型的二维码场景值，具体看文档
        $ticket = $wxService->getEverlastingQrCodeTicket($accessToken, $data);
        // 得到二维码
        $src = 'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket='.urlencode($ticket);
        echo "<img src='".$src."' />";
    }

    /**
     * 测试
     */
    public function test()
    {
        dd(WxService::generateBaseCharArr(true, true, true, true));
    }
}
