<?php
    require_once ("cms/classes/redsysHMAC256_API_PHP_4.0.2/apiRedsys.php");
    require ('cms/classes/class.general.php');
    require ('cms/classes/class.encryption.php');
    require ('cms/classes/class.plantilla.php');

    // ini_set('display_errors', 1);
    // error_reporting(E_ALL);
    $oGeneral = new general();
    $oEncryption = new Encryption();
    $token = json_decode($oEncryption->decrypt($_GET['token']));

    if(empty($token) || $token->id_hotel > 0){
        $token->id_hotel = filter_var($token->id_hotel, FILTER_SANITIZE_NUMBER_INT);
        $token->cod_reserva = filter_var($token->cod_reserva, FILTER_SANITIZE_NUMBER_INT);

        $oGeneral->writeInfoLog('Payments', 'token', var_export($token, true));
        $url = "https://www.grupotel.net/modulo2012/reservasonline.php?pas=payments&id_hotel=".$token->id_hotel;

        $ch	= curl_init ($url);
        curl_setopt ($ch, CURLOPT_URL, $url);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, 15);
        // curl_setopt ($ch, CURLOPT_HTTPHEADER, false);
        curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, true);
        $res = curl_exec($ch);
        curl_close($ch);
        //$info = curl_getinfo($ch);

        $xml = simplexml_load_string($res);
        $oGeneral->writeInfoLog('Payments', 'xml', var_export($xml, true));
        
        if(!empty($xml->Tpv)){
            $id_reserva	= $token->cod_reserva;
            $cs			= 'HL'.$id_reserva.date("YmdHis");
            $importe	= number_format(floatval($token->importe_total), 2, '', '');
            $params		= "id_cs={$cs}&id_reserva=$id_reserva&id_canal=".$token->id_canal."&lang=".$_POST['lang']."&ok=X&pg=payments_final&test=0";
            $urlTpv		= "https://www.grupotel.com/index.php?";
            $urlOk		= $urlTpv . str_replace('ok=X', 'ok=s', $params);
            $urlNok		= $urlTpv . str_replace('ok=X', 'ok=n', $params);
    
            $oRedSys = new RedsysAPI;
    
            $cod = str_replace('0', '' , date("ymdHis"));
            $cod .= rand();
            $codOperacion = substr($cod, 0, 12);
            
            $idiomas	= array('es' => '001', 'en' => '002', 'de' => '005');
            $idioma = empty($idiomas[$token->lang]) ? $idiomas['es'] : $idiomas[$token->lang];
    
            $p = new plantilla ('plantillas/form-redsys.html');
        
            $p->add('url', trim($xml->Tpv->PaymentUrl));
        
            $oRedSys->setParameter('DS_MERCHANT_AMOUNT',			$importe);
            $oRedSys->setParameter('DS_MERCHANT_ORDER',				"$codOperacion");
            $oRedSys->setParameter('DS_MERCHANT_MERCHANTCODE',		trim($xml->Tpv->MerchantId));
            $oRedSys->setParameter('DS_MERCHANT_MERCHANTDATA',		'cs=' . $cs);
            $oRedSys->setParameter('DS_MERCHANT_CURRENCY',			'978');
            $oRedSys->setParameter('DS_MERCHANT_TRANSACTIONTYPE',	'0');
            $oRedSys->setParameter('DS_MERCHANT_TERMINAL',			trim ($xml->Tpv->TerminalId));
            $oRedSys->setParameter('DS_MERCHANT_MERCHANTURL',		'https://www.grupotel.net/checktpv.php?pg=cyberpac');
            $oRedSys->setParameter('DS_MERCHANT_URLOK',				$urlOk);
            $oRedSys->setParameter('DS_MERCHANT_URLKO',				$urlNok);
            $oRedSys->setParameter('DS_MERCHANT_CONSUMERLANGUAGE',	$idioma);
        
            $p->add('parameters', $oRedSys->createMerchantParameters());
            $p->add('firma', $oRedSys->createMerchantSignature($xml->Tpv->PasswordAdmin));		
            $rs = $p->toString();
            echo $rs;
        }else{
            $oGeneral->writeInfoLog('Payments', 'No autorizado', var_export($token, true));
            header('Location: ' . $_SERVER['HTTP_REFERER']);
        }
    }else{
        $oGeneral->writeInfoLog('Payments', 'No autorizado', var_export($token, true));
        header('Location: ' . $_SERVER['HTTP_REFERER']);
    }
?>
<script>
    var el = document.getElementById('form-tpv');
    if(typeof el !== 'undefined') el.submit();
</script>
