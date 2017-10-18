<?php
require('StarSQL.php');
require("fanfou.php");
function GetEarthquake()
{
	$url='http://news.ceic.ac.cn/'; #网址url
	$ct=Geturl($url); #Get 方式获取网址
	$par='/<div class="news-content" id="news">(.*)<\/div>/si';
	preg_match($par,$ct,$match); #取新闻中心部分
	$txt=explode('</tr>',$match[1]);
	$i=0;
	$res=array();
	foreach($txt as $tmp)
	{
		
		if($i==0) 
		{	
			$i++;
			continue;
		}
		$tmp=preg_replace('/<td.*?>/','',$tmp);
		$tmp=preg_replace('/<tr.*?>/','',$tmp);
		#$tmp=str_replace('',"",$tmp);
		$tmp2=explode('</td>',$tmp);
		if(count($tmp2)<6) continue;
		preg_match('/<a href="(.*)">(.*)</',$tmp2[5],$tmp3);
		$tp['level']=trim(str_replace("'","",$tmp2[0])); #震级
		$tp['time']=trim(str_replace("'","",$tmp2[1]));  #时间
		$tp['lat']=trim(str_replace("'","",$tmp2[2]));  #纬度
		$tp['lon']=trim(str_replace("'","",$tmp2[3]));   #经度
		$tp['depth']=trim(str_replace("'","",$tmp2[4]));  #震源深度
		$tp['url']=trim(str_replace("'","",$tmp3[1]));  #详细描述网址
		$tp['address']=trim(str_replace("'","",$tmp3[2]));  #震源位置
		$tp['md5']=md5($tp['url'].$tp['level'].$tp['time']);  #地震唯一md5标识
		$res[]=$tp;
		$i++;
	}
	return $res; #返回数组
}
StarSQL::SetConfig("config.php");
$me=StarSQL::GetByastron("content",array("name"=>"eq"));
if(!is_array($me))
	exit;
$txt=$me[0]['content']; #数据库拉取上次发布地震消息的md5
$eqs=GetEarthquake();
$presend=array();
foreach($eqs as $tmp)
{
	if($tmp['md5']!=$txt) #从最新向后过滤，若md5值与上次发布不同，视为新地震
	{
		if($tmp['lat']>0)
			$a="北纬".$tmp['lat']."度";
		else
			$a="南纬".abs($tmp['lat'])."度";
		if($tmp['lon']>0)
			$b="东经".$tmp['lon']."度";
		else
			$b="西经".abs($tmp['lon'])."度";
		$k=mktime(substr($tmp['time'],11,2),substr($tmp['time'],14,2),substr($tmp['time'],17,2),substr($tmp['time'],5,2),substr($tmp['time'],8,2),substr($tmp['time'],0,4));
		if(time()-$k>100000) #地震时间与当今时间超过过久，视为抓取错误
			break;
		$time=substr($tmp['time'],0,4)."年".substr($tmp['time'],5,2)."月".substr($tmp['time'],8,2)."日"
		.substr($tmp['time'],11,2)."时".substr($tmp['time'],14,2)."分".substr($tmp['time'],17,2)."秒";
		$fb=$time."，".$tmp['address']."（".$a."，".$b."）"."发生".$tmp['level']."级地震，震源深度".$tmp['depth']."千米。 ".$tmp['url']
		." via 中国地震台网";
		$presend[]=$fb; # $presend存储着预发送信息
	}else
		break;
}
if(count($presend)>0)
	$md5=$eqs[0]['md5'];
$presend=array_reverse($presend); #按时间顺序倒置
$taiyou=new fanfou('key1','key2'); 
$taiyou->oauth_token='token';
$taiyou->oauth_secret='secret'; #OAuth认证
$j=0;
foreach($presend as $tmp)
{
	$i=0;
	do
	{
		$p=$taiyou->SendSMS($tmp);
		$i++;
		sleep(1);
	}
	while(preg_match("/created_at/",(string)$p)==false && $i<5); #尝试发送，若出错，重试5次
	if($i>=5)
		exit;
	$j++;
	if($j>3)
		break;
}
if(count($presend)>0)
	StarSQL::Updateastron(array("content"=>$md5),array("name"=>"eq")); #发送成功后更新数据库
echo "ok";