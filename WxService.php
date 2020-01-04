<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class WxService
{
    protected $obj;
    protected $fromUser;
    protected $toUser;
    protected $createTime;

    /**
     * WxService constructor.
     */
    public function __construct()
    {

    }

    /**
     * 使用 curl 调用接口
     *
     * @param $url
     * @param string $method
     * @param string $res
     * @param string $arr
     * @return bool|string
     */
    public static function httpCurl($url, $method = 'get', $res = 'json', $arr = '')
    {
        // 1. 初始化 curl
        $ch = curl_init();
        // 2. 设置 curl 参数
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if ($method == 'post') {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $arr);
        }
        // 3. 采集
        $output = curl_exec($ch);
        // 4. 关闭
//        curl_close($ch);

        if ($res == 'json') {
            if (curl_errno($ch)) {
                return curl_error($ch);
            } else {
                return json_decode($output, true);
            }
        }
        return $output;
    }

    /**
     * 生成基础字符数组，兼容特殊字符
     *
     * @param bool $lower
     * @param bool $upper
     * @param bool $num
     * @param bool $spec
     * @return array
     */
    public static function generateBaseCharArr($lower = true, $upper = false, $num = false, $spec = false)
    {
        $lowerChar = [];
        $upperChar = [];
        $numeral = [];
        // 特殊字符
        // 33->!, 35->#, 36->$, 38->&, 42->*, 43->+, 45->-, 64->@, 95->_
        $specChar = [33, 35, 36, 38, 42, 43, 45, 64, 95];
        // 生成小写字符
        for ($i = 97; $i <= 122; $i++ ) {
            array_push($lowerChar, $i);
        }
        // 生成大写字符
        for ($i = 65; $i <= 90; $i++ ) {
            array_push($upperChar, $i);
        }
        // 生成 0-9 数字
        for ($i = 48; $i <= 57; $i++ ) {
            array_push($numeral, $i);
        }
        $asciiArr = [];
        if ($lower) {
            $asciiArr = array_merge($asciiArr, $lowerChar);
        }
        if ($upper) {
            $asciiArr = array_merge($asciiArr, $upperChar);
        }
        if ($num) {
            $asciiArr = array_merge($asciiArr, $numeral);
        }
        if ($spec) {
            $asciiArr = array_merge($asciiArr, $specChar);
        }
        $charArr = [];
        foreach ($asciiArr as $v) {
            array_push($charArr, chr($v));
        }
        shuffle($charArr);
        return $charArr;
    }

    /**
     * 获取随机字符串
     *
     * @param $length
     * @return string
     * @throws \Exception
     */
    public static function getNonceStr($length)
    {
        $charArr = self::generateBaseCharArr(true, true, true);
        $str = '';
        $count = count($charArr);
        for ($i = 0; $i < $length; $i++) {
            $offset = random_int(0, $count - 1);
            $str .= $charArr[$offset];
        }
        return $str;
    }

    /**
     * 回复消息
     * 
     * @param $obj
     * @param $msgType
     * @param $data
     * @return string
     */
    public function responseMsg($obj, $msgType, $data)
    {
        try {
            $this->handleAttr($obj);
        } catch (\Exception $e) {
            Log::info('构建消息体属性异常：' . $e->getMessage());
            return '';
        }
        switch ($msgType) {
            case 'text': return $this->handleText($msgType, $data); break;
            case 'news': return $this->handleNews($msgType, $data); break;
            default:
                Log::info('未知类型或未处理类型：' . $msgType);
                return '';
                break;
        }
    }

    /**
     * 构建消息体属性
     *
     * @param $obj
     * @throws \Exception
     */
    protected function handleAttr($obj)
    {
        if (!isset($obj->ToUserName)) {
            throw new \Exception('属性 ToUserName 不存在');
        } else if (!isset($obj->FromUserName)) {
            throw new \Exception('属性 FromUserName 不存在');
        }
        $this->obj = $obj;
        $this->fromUser = $obj->ToUserName;
        $this->toUser = $obj->FromUserName;
        $this->createTime = time();
    }

    /**
     * 处理文本消息类型回复
     * 
     * @param $msgType
     * @param $data
     * @return string
     */
    protected function handleText($msgType, $data)
    {
        $template = "<xml>
                         <ToUserName><![CDATA[%s]]></ToUserName>
                         <FromUserName><![CDATA[%s]]></FromUserName>
                         <CreateTime>%s</CreateTime>
                         <MsgType><![CDATA[%s]]></MsgType>
                         <Content><![CDATA[%s]]></Content>
                     </xml>";
        return sprintf($template, $this->toUser, $this->fromUser, $this->createTime, $msgType, $data);
    }

    /**
     * 处理图文消息类型回复
     * 注：2020-01-01记录 图文消息个数；当用户发送文本、图片、视频、图文、地理位置这五种消息时，
     *      开发者只能回复1条图文消息；其余场景最多可回复8条图文消息
     * @param $msgType
     * @param $data
     * @return string
     */
    protected function handleNews($msgType, $data)
    {
        $template = "<xml>
                        <ToUserName><![CDATA[%s]]></ToUserName>
                        <FromUserName><![CDATA[%s]]></FromUserName>
                        <CreateTime>%s</CreateTime>
                        <MsgType><![CDATA[%s]]></MsgType>
                        <ArticleCount>".count($data)."</ArticleCount>
                        <Articles>";
        foreach ($data as $k => $v) {
            $template .= "<item>
                            <Title><![CDATA[".$v['title']."]]></Title>
                            <Description><![CDATA[".$v['description']."]]></Description>
                            <PicUrl><![CDATA[".$v['picUrl']."]]></PicUrl>
                            <Url><![CDATA[".$v['url']."]]></Url>
                          </item>";
        }
        $template .= "</Articles>
                 </xml>";
        return sprintf($template, $this->toUser, $this->fromUser, $this->createTime, $msgType);
    }

    /**
     * 获取网页授权 code
     * 订阅号没有接口权限
     * @param $scope
     * @param $redirectUri
     */
    public function getOauthCode($scope, $redirectUri)
    {
        // snsapi_base: 静默授权, snsapi_base: 显式授权
        $url = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid='.env('WX_APP_ID').'&redirect_uri='.urlencode($redirectUri).'&response_type=code&scope='.$scope.'&state=STATE#wechat_redirect';
        header('Location: ' . $url);
    }

    /**
     * 微信网页授权通过 code 获取 access_token
     * @param $code
     * @return bool|string
     */
    public function getAccessTokenByCode($code)
    {
        $url = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid='.env('WX_APP_ID').'&secret='.env('WX_APP_SECRET').'&code='.$code.'&grant_type=authorization_code';
        return self::httpCurl($url);
    }

    /**
     * 根据 access_token 获取微信用户详细信息
     *
     * @param $accessToken
     * @param $openId
     * @return bool|string
     */
    public function getUserInfoByAccessToken($accessToken, $openId)
    {
        $url = "https://api.weixin.qq.com/sns/userinfo?access_token=".$accessToken."&openid=".$openId."&lang=zh_CN";
        return self::httpCurl($url);
    }

    /**
     * 获取微信全局调用接口 access_token
     *
     * @return bool|string
     */
    public function getWxGlobalAccessToken()
    {
        if (session('access_token') && session('access_token_expire_time') && session('access_token_expire_time') > time()) {
            return session('access_token');
        } else {
            // fixme 调试原因，使用测试号 appId 和 appSecret
            $testAppId = 'wxea34394d6d4b5acd';
            $testAppSecret = '22614b2d335ccf23dac775698ddb952f';
            $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.$testAppId.'&secret='.$testAppSecret;
            $accessToken = self::httpCurl($url);
            // 将得到的 access_token 缓存进 session 中
            session('access_token', $accessToken['access_token']);
            session('access_token_expire_time', time() + 7200);
            return $accessToken['access_token'];
        }
    }

    /**
     * 获取微信服务器 IP 列表
     * 主要用于第三方服务器对数据是否来自于微信服务器进行安全验证和过滤
     *
     * @param $accessToken
     * @return bool|string
     */
    public function getWxServerIpList($accessToken)
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/getcallbackip?access_token='.$accessToken;
        return self::httpCurl($url);
    }

    /**
     * 创建菜单
     *
     * @param $accessToken
     * @param $menu
     * @return bool|string
     */
    public function createMenu($accessToken, $menu)
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/menu/create?access_token='.$accessToken;
        /**
         * JSON_UNESCAPED_SLASHES 不转义反斜杠
         * JSON_UNESCAPED_UNICODE 中文不转为unicode
         */
        return self::httpCurl($url, 'post', 'json', json_encode($menu, JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_UNICODE));
    }

    /**
     * 群发消息
     *
     * @param $accessToken
     * @param $msgType
     * @param $data
     * @return bool|string
     * @throws \Exception
     */
    public function groupMsgSend($accessToken, $msgType, $data)
    {
        // todo 待优化，根据标签进行群发，根据 openid 进行群发
        // 校验群发类型（单文本「text」, 图文消息「mpnews」, 音频消息「voice」, 图片消息「image」，视频消息「mpvideo」，卡券消息「wxcard」）
        $types = ['text', 'mpnews', 'voice', 'image', 'mpvideo', 'wxcard'];
        if (!in_array($msgType, $types)) {
            throw new \Exception('群发消息类型有误，请检查');
        }
        switch ($msgType) {
            case 'text': return $this->handleGroupMsgText($accessToken, $data); break;
            case 'mpnews': return $this->handleGroupMsgMpNews(); break;
            case 'voice': return $this->handleGroupMsgVoice(); break;
            case 'image': return $this->handleGroupMsgImage(); break;
            case 'mpvideo': return $this->handleGroupMsgMpVideo(); break;
            case 'wxcard': return $this->handleGroupMsgWxCard(); break;
            default: break;
        }
    }

    /**
     * 处理群发单文本消息
     * @param $accessToken
     * @param $data
     * @return bool|string
     */
    private function handleGroupMsgText($accessToken, $data)
    {
        // fixme 由于调试，下面使用的是预览接口
        $url = 'https://api.weixin.qq.com/cgi-bin/message/mass/preview?access_token='.$accessToken;
        /**
         * JSON_UNESCAPED_SLASHES 不转义反斜杠
         * JSON_UNESCAPED_UNICODE 中文不转为unicode
         */
        return self::httpCurl($url, 'post', 'json', json_encode($data, JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_UNICODE));
    }

    /**
     * 处理群发图文消息
     */
    private function handleGroupMsgMpNews()
    {
        // todo 待完善 code...
        return '';
    }

    /**
     * 处理群发音频消息
     */
    private function handleGroupMsgVoice()
    {
        // todo 待完善 code...
        return '';
    }

    /**
     * 处理群发图片消息
     */
    private function handleGroupMsgImage()
    {
        // todo 待完善 code...
        return '';
    }

    /**
     * 处理群发视频消息
     */
    private function handleGroupMsgMpVideo()
    {
        // todo 待完善 code...
        return '';
    }

    /**
     * 处理群发卡券消息
     */
    private function handleGroupMsgWxCard()
    {
        // todo 待完善 code...
        return '';
    }

    /**
     * 上传图文消息内的图片
     * @param $accessToken
     * @param $filename
     * @return bool|string
     */
    public function uploadImage($accessToken, $filename)
    {
        /**
         * 注意点：
         * 1、传递媒体参数只需要文件名即可（文件名应该为入口文件根目录的路径开始一直到文件的路径）
         * 2、不需要 json_encode
         */
        $url = 'https://api.weixin.qq.com/cgi-bin/media/uploadimg?access_token='.$accessToken;
        $data = ['media' => '@'.$filename];
        $res = self::httpCurl($url, 'post', 'json', $data);
        return $res['url'];
    }

    /**
     * 上传图文消息素材
     *
     * @param $accessToken
     * @param $data
     * @return bool|string
     */
    public function uploadMpNewsMaterial($accessToken, $data)
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/media/uploadnews?access_token='.$accessToken;
        return self::httpCurl($url, 'post', 'json', $data);
    }

    /**
     * 查询群发消息发送状态
     *
     * @param $accessToken
     * @param $msgId
     * @return bool|string
     */
    public function getGroupMsgSendStatus($accessToken, $msgId)
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/message/mass/get?access_token='.$accessToken;
        return self::httpCurl($url, 'post', 'json', json_encode(['msg_id' => $msgId]));
    }

    /**
     * 发送模板消息
     *
     * @param $accessToken
     * @param $data
     * @return bool|string
     */
    public function sendTemplateMsg($accessToken, $data)
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/message/template/send?access_token='.$accessToken;
        return self::httpCurl($url, 'post', 'json', json_encode($data, JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_UNICODE));
    }

    /**
     * 获取 jsapi_ticket
     *
     * @param $accessToken
     * @return mixed
     */
    public function getJsApiTicket($accessToken)
    {
        if (session('jsapi_ticket') && session('jsapi_ticket_expire_time') && session('jsapi_ticket_expire_time') > time()) {
            return session('jsapi_ticket');
        } else {
            $url = 'https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token='.$accessToken.'&type=jsapi';
            $res = self::httpCurl($url);
            // 微信的 jsapi_ticket 的有效时间为 7200，所以本地也要进行有效性缓存
            session('jsapi_ticket', $res['ticket']);
            session('jsapi_ticket_expire_time', 7200);

            // todo 待完善，请求失败 errcode != 0 || errmsg != 'ok' ?
            return $res['ticket'];
        }
    }

    /**
     * 获取创建临时二维码的 ticket
     *
     * @param $accessToken
     * @param $data
     * @return mixed
     */
    public function getTempQrCodeTicket($accessToken, $data)
    {
        if (session('temp_qr_code_ticket') && session('temp_qr_code_ticket_expire_time') && session('temp_qr_code_ticket_expire_time') > time()) {
            return session('temp_qr_code_ticket');
        } else {
            $url = 'https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token='.$accessToken;
            $res = self::httpCurl($url, 'post', 'json', json_encode($data));
            session('temp_qr_code_ticket', $res['ticket']);
            if (isset($res['expire_seconds'])) {
                session('temp_qr_code_ticket_expire_time', $res['expire_seconds']);
            }
            return $res['ticket'];
        }
    }

    /**
     * 获取创建永久二维码的 ticket
     * @param $accessToken
     * @param $data
     * @return mixed
     */
    public function getEverlastingQrCodeTicket($accessToken, $data)
    {
        if (session('ever_qr_code_ticket') && session('ever_qr_code_ticket_expire_time') && session('ever_qr_code_ticket_expire_time') > time()) {
            return session('qr_code_ticket');
        } else {
            $url = 'https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token='.$accessToken;
            $res = self::httpCurl($url, 'post', 'json', json_encode($data));
            session('ever_qr_code_ticket', $res['ticket']);
            if (isset($res['expire_seconds'])) {
                session('ever_qr_code_ticket_expire_time', $res['expire_seconds']);
            }
            return $res['ticket'];
        }
    }
}