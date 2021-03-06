<?php
namespace App\Http\Controllers\WX;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\wxmodel;
use App\Model\WxUserModel;
use App\wx\ImgModel;
use App\wx\VideoModel;
use App\wx\VoiceModel;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Redis;
class WXController extends Controller
{
    protected $access_token;
    public function __construct()
    {
        $this->access_token = $this->getaccess_token();
    }
    public function getaccess_token()
    {
        // $key = 'wx_access_token';
        // $access_token = Redis::get($key);
        // if($access_token){
        //     return $access_token;
        // }
        $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=' . env('APPID') . '&secret=' . env('APPSECRET');
       // echo $url;die;
        $data_json = file_get_contents($url);
        $arr = json_decode($data_json, true);

         // Redis::set($key,$arr['access_token']);
         // Redis::expire($key,3600);
         return $arr['access_token'];
        $a='28_ZVfNocoSaEQl2i7S8KxuMhs8TXXNhtbTb6vkcOUuvKg5olzozR-xp1Kb8rTRqSFxpsq10sfZHCijuvAObG-Lq-o7Xltgc4k3Z9s4s8m88GvmHIsEx0aqB2bS1iF0YlpMYntDEzsPChyajr7hOXSaAGAUDY';
        return $a;
    }
    public function phpinfo()
    {         
        phpinfo();
    }
    public function wx()
    {
        $token = 'abc123token';       //开发提前设置好的 token
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];
        $echostr = $_GET["echostr"];
        $tmpArr = array($token, $timestamp, $nonce);
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode($tmpArr);
        $tmpStr = sha1($tmpStr);
        if ($tmpStr == $signature) {        //验证通过
            echo $echostr;
        } else {
            die("not ok");
        }
    }
    public function receiv()
    {
        $log_file = "wx.log";
        //将接收 _contents($log_file, $data, FILE_APPEND);//追加写
        //2019-12-23 17:28:11

 		$xml_str = file_get_contents("php://input");
        $data = date('Y-m-d H:i:s') . $xml_str;
        file_put_contents($log_file, $data, FILE_APPEND);//追加写
        $xml_obj = simplexml_load_string($xml_str);
        $event = $xml_obj->Event;
        $content = date('Y-m-d H:i:s') . $xml_obj->Content;
        if ($event == 'subscribe') {
            $openid = $xml_obj->FromUserName;  //获取用户的openid
            $res = WxUserModel::where(['openid' => $openid])->first();
//            dd($res);
            if(!empty($res)) {
//                dd(11);
                $content = '欢迎回来哦';
                $response_text =
'<xml>
    <ToUserName><![CDATA[' . $openid . ']]></ToUserName>
    <FromUserName><![CDATA[' . $xml_obj->ToUserName . ']]></FromUserName>
    <CreateTime>' . time() . '</CreateTime> 
    <MsgType><![CDATA[text]]></MsgType>
    <Content><![CDATA[' . $content . ']]></Content>
</xml>';
//                dd($response_text);
                echo $response_text;        //回复用户消息
            } else {
                //获取用户信息
                $url = 'https://api.weixin.qq.com/cgi-bin/user/info?access_token=' . $this->access_token . '&openid=' . $openid . '&lang=zh_CN';
                $user_info = file_get_contents($url);
                $u = json_decode($user_info, true);
                
                $data = [
                    'openid' => $openid,
                    'sub_time' => time(),
                    'sex' => $u['sex'],
                    'nickname' => $u['nickname'],
                    'img' => $u['headimgurl'],
                ];
                //openid入库
                $uid = WxUserModel::insertGetId($data);
                file_put_contents('wx_user.log', $user_info, FILE_APPEND);
                $content = '欢迎关注哦';
                $response_text = '<xml>
                        <ToUserName><![CDATA[' . $openid . ']]></ToUserName>
                        <FromUserName><![CDATA[' . $xml_obj->ToUserName . ']]></FromUserName>
                        <CreateTime>' . time() . '</CreateTime> 
                        <MsgType><![CDATA[text]]></MsgType>
                        <Content><![CDATA[' . $content . ']]></Content>
                        </xml>';
                echo $response_text;        //回复用户消息
            }
        }elseif($event=='CLICK'){
            if($xml_obj->EventKey=='weather'){
                //如果是 获取天气
                //请求第三方接口 获取天气
                $url = 'https://api.weixin.qq.com/cgi-bin/user/info?access_token=' . $this->access_token . '&openid=' . $xml_obj->FromUserName . '&lang=zh_CN';
                $user_info = file_get_contents($url);
                $u = json_decode($user_info, true);
                // dd($u);
                $location=$u['city'];
                $weather_api = 'https://free-api.heweather.net/s6/weather/now?location='.$location.'&key=b2e92f2df77e48b3a36f20e912b796d9';
                $weather_info = file_get_contents($weather_api);
                $weather_info_arr = json_decode($weather_info,true);
//                print_r($weather_info_arr);die;
                $cond_txt = $weather_info_arr['HeWeather6'][0]['now']['cond_txt'];
                $tmp = $weather_info_arr['HeWeather6'][0]['now']['tmp'];
                $wind_dir = $weather_info_arr['HeWeather6'][0]['now']['wind_dir'];
                $msg = $cond_txt . ' 温度： '.$tmp . ' 风向： '. $wind_dir;
                $response_xml = '<xml>
                <ToUserName><![CDATA['.$xml_obj->FromUserName.']]></ToUserName>
                <FromUserName><![CDATA['.$xml_obj->ToUserName.']]></FromUserName>
                <CreateTime>'.time().'</CreateTime>
                <MsgType><![CDATA[text]]></MsgType>
                <Content><![CDATA['. date('Y-m-d H:i:s') .  $msg .']]></Content>
                </xml>';
                echo $response_xml;
            }elseif($xml_obj->EventKey=='1905wx_toupiao'){
            }
        }
        //判断消息类型
        $msg_type = $xml_obj->MsgType;
        $touser = $xml_obj->FromUserName;         //接收消息的用户的id
        $fromuser = $xml_obj->ToUserName;         //开发者公众号的ID
        $time = time();
        $media_id=$xml_obj->MediaId;
//        dd($media_id);
        if ($msg_type == 'text') {
            $response_text = '<xml>
        <ToUserName><![CDATA[' . $touser . ']]></ToUserName>
        <FromUserName><![CDATA[' . $fromuser . ']]></FromUserName>
        <CreateTime>' . $time . '</CreateTime> 
        <MsgType><![CDATA[text]]></MsgType>
        <Content><![CDATA[' . $content . ']]></Content>
        </xml>';
            echo $response_text;        //回复用户消息
        }elseif($msg_type=='image'){    // 图片消息
            // TODO 下载图片
            $this->getMedia2($media_id,$msg_type);
            // TODO 回复图片
            $response = '<xml>
  <ToUserName><![CDATA['.$touser.']]></ToUserName>
  <FromUserName><![CDATA['.$fromuser.']]></FromUserName>
  <CreateTime>'.time().'</CreateTime>
  <MsgType><![CDATA[image]]></MsgType>
  <Image>
    <MediaId><![CDATA['.$media_id.']]></MediaId>
  </Image>
</xml>';
            echo $response;
        }elseif($msg_type=='voice'){          // 语音消息
            // 下载语音
            $this->getMedia2($media_id,$msg_type);
            // TODO 回复语音
            $response = '<xml>
  <ToUserName><![CDATA['.$touser.']]></ToUserName>
  <FromUserName><![CDATA['.$fromuser.']]></FromUserName>
  <CreateTime>'.time().'</CreateTime>
  <MsgType><![CDATA[voice]]></MsgType>
  <Voice>
    <MediaId><![CDATA['.$media_id.']]></MediaId>
  </Voice>
</xml>';
            echo $response;
        }elseif($msg_type=='video'){
            // 下载小视频
            $this->getMedia2($media_id,$msg_type);
            // 回复
            $response = '<xml>
  <ToUserName><![CDATA['.$touser.']]></ToUserName>
  <FromUserName><![CDATA['.$fromuser.']]></FromUserName>
  <CreateTime>'.time().'</CreateTime>
  <MsgType><![CDATA[video]]></MsgType>
  <Video>
    <MediaId><![CDATA['.$media_id.']]></MediaId>
    <Title><![CDATA[测试]]></Title>
    <Description><![CDATA[不可描述]]></Description>
  </Video>
</xml>';
            echo $response;
        }
    }
    public function getMedia($media_id)
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/media/get?access_token='.$this->access_token.'&media_id='.$media_id;
        //获取素材内容
        $data = file_get_contents($url);
        // 保存文件
        $file_name = date('YmdHis').mt_rand(11111,99999) . '.amr';
        file_put_contents($file_name,$data);
        echo "下载素材成功";echo '</br>';
        echo "文件名： ". $file_name;
    }
    /**
     * 获取素材
     */
    protected function getMedia2($media_id,$media_type)
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/media/get?access_token='.$this->access_token.'&media_id='.$media_id;
        //获取素材内容
        $client = new Client();
        $response = $client->request('GET',$url);
        //获取文件扩展名
        $f = $response->getHeader('Content-disposition')[0];
        $extension = substr(trim($f,'"'),strpos($f,'.'));
        //获取文件内容
        $file_content = $response->getBody();
        // 保存文件
        $save_path = 'wx_media/';
        if($media_type=='image'){       //保存图片文件
            $file_name = date('YmdHis').mt_rand(11111,99999) . $extension;
            $save_path = $save_path . 'imgs/' . $file_name;
            $data=['img'=>$save_path];
            ImgModel::insert($data);
        }elseif($media_type=='voice'){  //保存语音文件
            $file_name = date('YmdHis').mt_rand(11111,99999) . $extension;
            $save_path = $save_path . 'voice/' . $file_name;
            $data=['voice'=>$save_path];
            voiceModel::insert($data);
        }elseif($media_type=='video')
        {
            $file_name = date('YmdHis').mt_rand(11111,99999) . $extension;
            $save_path = $save_path . 'video/' . $file_name;
            $data=['video'=>$save_path];
            VideoModel::insert($data);
        }
        file_put_contents($save_path,$file_content);
    }
    /**
     * 创建自定义菜单
     */
    public function createMenu()
    {
        $url = 'http://1905yangmf.comcto.com/vote';
        $redirect_uri = urlencode($url);        //授权后跳转页面
        //创建自定义菜单的接口地址
        $url = 'https://api.weixin.qq.com/cgi-bin/menu/create?access_token='.$this->access_token;
        $menu = [
            'button'    => [
                [
                    'type'  => 'click',
                    'name'  => '天气',
                    'key'   => 'weather'
                ],
                [
                    'type'  => 'view',
                    'name'  => '投票',
                    'url'   =>'https://open.weixin.qq.com/connect/oauth2/authorize?appid=wx9458fefe0c30d65b&redirect_uri='.$redirect_uri.'&response_type=code&scope=snsapi_userinfo&state=WX1905#wechat_redirect',
                ],
            ]
        ];
        $menu_json = json_encode($menu,JSON_UNESCAPED_UNICODE);
        $client = new Client();
        $response = $client->request('POST',$url,[
            'body'  => $menu_json
        ]);
        echo '<pre>';print_r($menu);echo '</pre>';
        echo $response->getBody();      //接收 微信接口的响应数据
    }
}