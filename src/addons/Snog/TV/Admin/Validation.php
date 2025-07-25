<?php

namespace Snog\TV\Admin;

class Validation
{
	public static function ValidateLanguage(&$value, \XF\Entity\Option $option)
	{
		$codes = "aa,ab,ae,af,ak,am,an,ar,as,av,ay,az,
			ba,be,bg,bh,bi,bm,bn,bo,br,bs,
			ca,ce,ch,co,cr,cs,cu,cv,cy,
			da,de,dv,dz,
			ee,el,en,eo,es,et,eu,
			fa,ff,fi,fj,fo,fr,fy,
			ga,gd,gl,gn,gu,gv,
			ha,he,hi,ho,hr,ht,hu,hy,hz,
			ia,id,ie,ig,ii,ik,io,is,it,iu,
			ja,jv,
			ka,kg,ki,kj,kk,kl,km,kn,ko,kr,ks,ku,kv,kw,ky,
			la,lb,lg,li,ln,lo,lt,lu,lv,
			mg,mh,mi,mk,ml,mn,mr,ms,mt,my,
			na,nb,nd,ne,ng,nl,nn,no,nr,nv,ny,
			oc,oj,om,or,os,
			pa,pi,pl,ps,pt,
			qu,
			rm,rn,ro,ru,rw,
			sa,sc,sd,se,sg,si,sk,sl,sm,sn,so,sq,sr,ss,st,su,sv,sw,
			ta,te,tg,th,ti,tk,tl,tn,to,tr,ts,tt,tw,ty,
			ug,ur,uz,
			ve,vi,vo,
			wa,wo,
			xh,
			yi,yo,
			za,zh,zu";

		$country_codes = "AD,AE,AF,AG,AI,AL,AM,AO,AQ,AR,AS,AT,AU,AW,AX,AZ,
			BA,BB,BD,BE,BF,BG,BH,BI,BJ,BL,BM,BN,BO,BQ,BR,BS,BT,BV,BW,BY,BZ,
			CA,CC,CD,CF,CG,CH,CI,CK,CL,CM,CN,CO,CR,CU,CV,CW,CX,CY,CZ,
			DE,DJ,DK,DM,DO,DZ,
			EC,EE,EG,EH,ER,ES,ET,
			FI,FJ,FK,FM,FO,FR,
			GA,GB,GD,GE,GF,GG,GH,GI,GL,GM,GN,GP,GQ,GR,GS,GT,GU,GW,GY,
			HK,HM,HN,HR,HT,HU,
			ID,IE,IL,IM,IN,IO,IQ,IR,IS,IT,
			JE,JM,JO,JP,
			KE,KG,KH,KI,KM,KN,KP,KR,KW,KY,KZ,
			LA,LB,LC,LI,LK,LR,LS,LT,LU,LV,LY,
			MA,MC,MD,ME,MF,MG,MH,MK,ML,MM,MN,MO,MP,MQ,MR,MS,MT,MU,MV,MW,MX,MY,MZ,
			NA,NC,NE,NF,NG,NI,NL,NO,NP,NR,NU,NZ,
			OM,
			PA,PE,PF,PG,PH,PK,PL,PM,PN,PR,PS,PT,PW,PY,
			QA,
			RE,RO,RS,RU,RW,
			SA,SB,SC,SD,SE,SG,SH,SI,SJ,SK,SL,SM,SN,SO,SR,SS,ST,SV,SX,SY,SZ,
			TC,TD,TF,TG,TH,TJ,TK,TL,TM,TN,TO,TR,TT,TV,TW,TZ,
			UA,UG,UM,US,UY,UZ,
			VA,VC,VE,VG,VI,VN,VU,
			WF,WS,
			YE,YT,
			ZA,ZM,ZW";

		$codes = str_replace(["\r", "\n", "\t"], '', $codes);
		$verify_codes = explode(',', $codes);
		$country_codes = str_replace(["\r", "\n", "\t"], '', $country_codes);
		$verify_country = explode(',', $country_codes);
		$countryCheck = '';

		if (stristr($value, '-'))
		{
			$options = explode('-', $value);
			$optionCheck = $options[0];
			$countryCheck = $options[1];
		}
		else
		{
			$optionCheck = $value;
		}

		if (!in_array(strtolower($optionCheck), $verify_codes))
		{
			$option->error(\XF::phrase('snog_tv_error_valid_639'));
			return false;
		}
		else
		{
			if ($countryCheck && !in_array(strtoupper($countryCheck), $verify_country))
			{
				$option->error(\XF::phrase('snog_tv_error_valid_3166'));
				return false;
			}
			else
			{
				return true;
			}
		}
	}

	public static function ValidateForum(&$value, \XF\Entity\Option $option)
	{
		if (($key = array_search('0', $value)) !== false) unset($value[$key]);

		if (!empty($value))
		{
			$finder = \XF::app()->finder('Snog\TV:Node');

			/** @var \Snog\TV\Entity\Node[] $tvForums */
			$tvForums = $finder->fetch();

			foreach ($tvForums as $tvForum)
			{
				if (!in_array($tvForum->node_id, $value))
				{
					$tvForum->delete();
				}
			}
		}

		return true;
	}
}
