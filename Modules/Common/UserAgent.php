<?php namespace islide\Modules\Common;


class UserAgent{
    
     public function init(){
        
    }
    
	public static function get_browsers($ua){
		$title = '非主流浏览器';
		$icon = 'ri-globe';
		if(preg_match('/rv:(11.0)/i', $ua, $matches)){
			$title = 'Internet Explorer '. $matches[1];
			$icon = 'ri-internet-explorer';//ie11
		}elseif (preg_match('#MSIE ([a-zA-Z0-9.]+)#i', $ua, $matches)) {
			$title = 'Internet Explorer '. $matches[1];
			
			if ( strpos($matches[1], '7') !== false || strpos($matches[1], '8') !== false)
				$icon = 'ri-internet-explorer';//ie8
			elseif ( strpos($matches[1], '9') !== false)
				$icon = 'ri-internet-explorer';//ie9
			elseif ( strpos($matches[1], '10') !== false)
				$icon = 'ri-internet-explorer';//ie10
		}elseif (preg_match('#Edg[A-Za-z]*/([a-zA-Z0-9.]+)#i', $ua, $matches)) {
			$title = 'Edge '. $matches[1];
			$icon = 'ri-edge';	
		}elseif (preg_match('#TheWorld ([a-zA-Z0-9.]+)#i', $ua, $matches)){
			$title = 'TheWorld(世界之窗) '. $matches[1];
			$icon = 'ri-theworld';
		}elseif (preg_match('#JuziBrowser#i', $ua, $matches)){
			$title = 'Juzi(桔子) ';//.$matches[1];
			$icon = 'ri-globe';
		}elseif (preg_match('#KBrowser#i', $ua, $matches)){
			$title = 'KBrowser(超快) ';//.$matches[1];
			$icon = 'ri-globe';
		}elseif (preg_match('#MyIE#i', $ua, $matches)){
			$title = 'MyIE(蚂蚁) ';//.$matches[1];
			$icon = 'ri-globe';
		}elseif (preg_match('#(?:Firefox|Phoenix|Firebird|BonEcho|GranParadiso|Minefield|Iceweasel)/([a-zA-Z0-9.]+)#i', $ua, $matches)){
			$title = 'Firefox '. $matches[1];
			$icon = 'ri-firefox';
		}elseif (preg_match('#CriOS/([a-zA-Z0-9.]+)#i', $ua, $matches)){
			$title = 'Chrome for iOS '. $matches[1];
			$icon = 'ri-chrome';
		} elseif (preg_match('#(?:LieBaoFast|LBBROWSER)/?([a-zA-Z0-9.]+)#i', $ua, $matches)) {
            $title = '猎豹 '. $matches[1];
			$icon = 'ri-liebaoliulanqi';
		}elseif (preg_match('#Opera.(.*)Version[ /]([a-zA-Z0-9.]+)#i', $ua, $matches)) {
			$title = 'Opera '. $matches[2];
			$icon = 'ri-opera';
			if (preg_match('#opera mini#i', $ua)) 
				$title = 'Opera Mini '. $matches[2];
		}elseif (preg_match('#OPR/([a-zA-Z0-9.]+)#i', $ua, $matches)) {
			$title = 'Opera '. $matches[1];
			$icon = 'ri-opera';
		}elseif (preg_match('#Maxthon( |\/)([a-zA-Z0-9.]+)#i', $ua,$matches)) {
			$title = 'Maxthon(遨游) '. $matches[2];
			$icon = 'ri-liulanqi-aoyou';
		}elseif (preg_match('/360/i', $ua, $matches)) {
			$title = '360浏览器';//放弃360怪异UA
			$icon = 'ri-browser-360';
			if (preg_match('/Alitephone Browser/i', $ua)) {
				$title = '360极速浏览器';
				$icon = 'ri-liulanqi-jisu';
			}
		}elseif (preg_match('#(?:SE |SogouMobileBrowser/)([a-zA-Z0-9.]+)#i', $ua, $matches)) {
			$title = '搜狗浏览器 '.$matches[1];
			$icon = 'ri-liulanqi-sougou';
		}elseif (preg_match('#QQ/([a-zA-Z0-9.]+)#i', $ua, $matches)) {
			$title = 'QQ '.$matches[1];
			$icon = 'ri-qq';
		}elseif (preg_match('#MicroMessenger/([a-zA-Z0-9.]+)#i', $ua,$matches)) {
			$title = '微信 '. $matches[1];
			$icon = 'ri-wechat';
		}elseif (preg_match('#QQBrowser/([a-zA-Z0-9.]+)#i', $ua, $matches)) {
			$title = 'QQ浏览器 '.$matches[1];
			$icon = 'ri-QQliulanqi';
		}elseif (preg_match('#YYE/([a-zA-Z0-9.]+)#i', $ua, $matches)) {
			$title = 'YY浏览器 '.$matches[1];
			$icon = 'ri-globe';
		}elseif (preg_match('#115Browser/([a-zA-Z0-9.]+)#i', $ua, $matches)) {
			$title = '115 '.$matches[1];
			$icon = 'ri-globe';
		}elseif (preg_match('#37abc/([a-zA-Z0-9.]+)#i', $ua, $matches)) {
			$title = '37abc '.$matches[1];
			$icon = 'ri-globe';
		}elseif (preg_match('#UCWEB([a-zA-Z0-9.]+)#i', $ua, $matches)) {
			$title = 'UC '. $matches[1];
			$icon = 'ri-ucliulanqi';
		}elseif (preg_match('#UC?Browser/([a-zA-Z0-9.]+)#i', $ua, $matches)) {
			$title = 'UC '. $matches[1];
			$icon = 'ri-ucliulanqi';
		}elseif (preg_match('#Quark/([a-zA-Z0-9.]+)#i', $ua, $matches)) {
			$title = '夸克 '. $matches[1];
			$icon = 'ri-kuakeliulanqi';
		}elseif (preg_match('#2345(?:Explorer|Browser)/([a-zA-Z0-9.]+)#i', $ua, $matches)) {
			$title = '2345浏览器 '. $matches[1];
			$icon = 'ri-globe';	
		}elseif (preg_match('#XiaoMi/MiuiBrowser/([0-9.]+)#i', $ua, $matches)) {
			$title = '小米 '. $matches[1];
			$icon = 'ri-xiaomi';	
		}elseif (preg_match('#SamsungBrowser/([0-9.]+)#i', $ua, $matches)) {
			$title = '三星 '. $matches[1];
			$icon = 'ri-globe';
		}elseif (preg_match('/WeiBo/i', $ua, $matches)) {
			$title = '微博 ';//. $matches[1];
			$icon = 'ri-weibo';
		}elseif (preg_match('/BIDU/i', $ua, $matches)) {
			$title = '百度 ';//. $matches[1];
			$icon = 'ri-browser-baidu';
		}elseif (preg_match('#baiduboxapp/([a-zA-Z0-9.]+)#i', $ua, $matches)) {
			$title = '百度 '. $matches[1];
			$icon = 'ri-browser-baidu';	
		}elseif (preg_match('#SearchCraft/([a-zA-Z0-9.]+)#i', $ua, $matches)) {
			$title = '简单搜索 '. $matches[1];
			$icon = 'ri-browser-baidu';
		}elseif (preg_match('#Qiyu/([a-zA-Z0-9.]+)#i', $ua, $matches)) {
			$title = '旗鱼浏览器 '. $matches[1];
			$icon = 'ri-globe';
		}elseif (preg_match('#mailapp/([a-zA-Z0-9.]+)#i', $ua, $matches)) {
			$title = '邮箱客户端 '. $matches[1];
			$icon = 'ri-globe';
		}elseif (preg_match('/Sleipnir/i', $ua, $matches)) {
			$title = '神马 ';//. $matches[1];
			$icon = 'ri-browser-shenma';
		}elseif (preg_match('/MZBrowser/i', $ua, $matches)) {
			$title = '魅族 ';//. $matches[1];
			$icon = 'ri-meizu';
		}elseif (preg_match('/VivoBrowser/i', $ua, $matches)) {
			$title = 'ViVO ';//. $matches[1];
			$icon = 'ri-VIVO';
		}elseif (preg_match('/mixia/i', $ua, $matches)) {
			$title = '米侠 ';//. $matches[1];
			$icon = 'ri-globe';
		}elseif (preg_match('#CoolMarket/([a-zA-Z0-9.]+)#i', $ua, $matches)) {
			$title = '酷安 '. $matches[1];//typecho ua获取不完整
			$icon = 'ri-coolapk';	
		}elseif (preg_match('#YaBrowser/([a-zA-Z0-9.]+)#i', $ua, $matches)) {
			$title = 'Yandex '. $matches[1];
			$icon = 'ri-yandex';	
		}elseif (preg_match('#Chrome/([a-zA-Z0-9.]+)#i', $ua, $matches)) {
			$title = 'Google Chrome '. $matches[1];
			$icon = 'ri-chrome';
		}elseif (preg_match('#Safari/([a-zA-Z0-9.]+)#i', $ua, $matches)) {
			$title = 'Safari '. $matches[1];
			$icon = 'ri-safari';
		}
		return array('title' => $title, 'icon' => $icon);
	}

    /**
     * 获取操作系统类型
     * @access public
     * @param $ua => $comments->agent
     * @return $array['title'] => 返回操作系统类型, $array['icon'] => 返回操作系统对应图标 
     */
	public static function get_os($ua){
		$title = '非主流操作系统';
		$icon = 'ri-search';
		if (preg_match('/win/i', $ua)) {
			if (preg_match('/Windows NT 6.1/i', $ua)) {
				$title = "Windows 7";
				$icon = "ri-win";
			}elseif (preg_match('/Windows 98/i', $ua)) {
				$title = "Windows 98";
				$icon = "ri-win2";
			}elseif (preg_match('/Windows NT 5.0/i', $ua)) {
				$title = "Windows 2000";
				$icon = "ri-win2";	
			}elseif (preg_match('/Windows NT 5.1/i', $ua)) {
				$title = "Windows XP";
				$icon = "ri-win";
			}elseif (preg_match('/Windows NT 5.2/i', $ua)) {
				if (preg_match('/Win64/i', $ua)) {
					$title = "Windows XP 64 bit";
				} else {
					$title = "Windows Server 2003";
				}
				$icon = 'ri-win';
			}elseif (preg_match('/Windows NT 6.0/i', $ua)) {
				$title = "Windows Vista";
				$icon = "ri-windows";
			}elseif (preg_match('/Windows NT 6.2/i', $ua)) {
				$title = "Windows 8";
				$icon = "ri-win8";
			}elseif (preg_match('/Windows NT 6.3/i', $ua)) {
				$title = "Windows 8.1";
				$icon = "ri-win8";
			}elseif (preg_match('/Windows NT 10.0/i', $ua)) {
				$title = "Windows 10";
				$icon = "ri-win3";
			}elseif (preg_match('/Windows Phone/i', $ua)) {
				$matches = explode(';',$ua);
				$title = $matches[2];
				$icon = "ri-winphone";
			}
		} elseif (preg_match('#iPod.*.CPU.([a-zA-Z0-9.( _)]+)#i', $ua, $matches)) {
			$title = "iPod ".str_replace('_', '.', $matches[1]);
			$icon = "ri-ipod";
		} elseif (preg_match('/iPhone OS ([_0-9]+)/i', $ua, $matches)) {
			$title = "iPhone ".str_replace('_', '.', $matches[1]);
			$icon = "ri-iphone";
		} elseif (preg_match('/iPad; CPU OS ([_0-9]+)/i', $ua, $matches)) {
			$title = "iPad ".str_replace('_', '.', $matches[1]);
			$icon = "ri-ipad";
		} elseif (preg_match('/Mac OS X ([0-9_]+)/i', $ua, $matches)) {
			if (count(explode(7,$matches[1]))>1) $matches[1] = 'Lion '.$matches[1];
			elseif (count(explode(8,$matches[1]))>1) $matches[1] = 'Mountain Lion '.$matches[1];
			$title = "Mac OS X ".str_replace('_', '.', $matches[1]);

			$icon = "ri-MacOS";
		} elseif (preg_match('/Macintosh/i', $ua)) {
			$title = "Mac OS";
			$icon = "ri-iconmacos";
		} elseif (preg_match('/CrOS/i', $ua)){
			$title = "Google Chrome OS";
			$icon = "ri-iconchromeos";
		} elseif (preg_match('/Android.([0-9. _]+)/i',$ua, $matches)) {
				$title= "Android " . $matches[1];
				$icon = "ri-android";	
		} elseif (preg_match('/Linux/i', $ua)) {
			$title = 'Linux';
			$icon = 'ri-linux';
			if (preg_match('/Ubuntu/i', $ua)) {
				$title = "Ubuntu Linux";
				$icon = "ri-ubuntu";
			} elseif (preg_match('#Debian#i', $ua)) {
				$title = "Debian GNU/Linux";
				$icon = "ri-debian";
			} elseif (preg_match('#Fedora#i', $ua)) {
				$title = "Fedora Linux";
				$icon = "ri-fedora";
			}
		}	
		return array('title' => $title, 'icon' => $icon);
	}
}