<?php
/**
* NOTA SOBRE LA LICENCIA DE USO DEL SOFTWARE
* 
* El uso de este software está sujeto a las Condiciones de uso de software que
* se incluyen en el paquete en el documento "Aviso Legal.pdf". También puede
* obtener una copia en la siguiente url:
* http://www.redsys.es/wps/portal/redsys/publica/areadeserviciosweb/descargaDeDocumentacionYEjecutables
* 
* Redsys es titular de todos los derechos de propiedad intelectual e industrial
* del software.
* 
* Quedan expresamente prohibidas la reproducción, la distribución y la
* comunicación pública, incluida su modalidad de puesta a disposición con fines
* distintos a los descritos en las Condiciones de uso.
* 
* Redsys se reserva la posibilidad de ejercer las acciones legales que le
* correspondan para hacer valer sus derechos frente a cualquier infracción de
* los derechos de propiedad intelectual y/o industrial.
* 
* Redsys Servicios de Procesamiento, S.L., CIF B85955367
*/

class RedsysAPI{

	/******  Array de DatosEntrada ******/
    var $vars_pay = array();
	
	/******  Set parameter ******/
	function setParameter($key,$value){
		$this->vars_pay[$key]=$value;
	}

	/******  Get parameter ******/
	function getParameter($key){
		return $this->vars_pay[$key];
	}
	
	
	//////////////////////////////////////////////////////////////////////////////////////////////
	//////////////////////////////////////////////////////////////////////////////////////////////
	////////////					FUNCIONES AUXILIARES:							  ////////////
	//////////////////////////////////////////////////////////////////////////////////////////////
	//////////////////////////////////////////////////////////////////////////////////////////////
	

	/******  3DES Function  ******/
	function encrypt_3DES($message, $key){
		// Compatibilitat HMAC_SHA256_V1 per callbacks ja iniciats abans del tall.
		$remainder = strlen($message) % 8;
		if ($remainder !== 0) {
			$message .= str_repeat("\0", 8 - $remainder);
		}
		$ciphertext = openssl_encrypt(
			$message,
			'des-ede3-cbc',
			$key,
			OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
			str_repeat("\0", 8)
		);
		if ($ciphertext === false) {
			throw new RuntimeException('REDSYS_3DES_SIGNATURE_ERROR');
		}
		return $ciphertext;
	}

	/****** AES-CBC Function for HMAC_SHA512_V2 ******/
	function encrypt_AES_V2($message, $key){
		$key = substr((string) $key, 0, 16);
		if (strlen($key) < 16) {
			$key = str_pad($key, 16, '0', STR_PAD_RIGHT);
		}
		$ciphertext = openssl_encrypt(
			$message,
			'aes-128-cbc',
			$key,
			OPENSSL_RAW_DATA,
			str_repeat("\0", 16)
		);
		if ($ciphertext === false) {
			throw new RuntimeException('REDSYS_AES_SIGNATURE_ERROR');
		}
		return $ciphertext;
	}

	/******  Base64 Functions  ******/
	function base64_url_encode($input){
		return strtr(base64_encode($input), '+/', '-_');
	}
	function base64_url_encode_safe($input){
		return rtrim(strtr(base64_encode($input), '+/', '-_'), '=');
	}
	function encodeBase64($data){
		$data = base64_encode($data);
		return $data;
	}
	function base64_url_decode($input){
		return base64_decode(strtr($input, '-_', '+/'));
	}
	function decodeBase64($data){
		$data = base64_decode($data);
		return $data;
	}

	/******  MAC Function ******/
	function mac256($ent,$key){
		$res = hash_hmac('sha256', $ent, $key, true);//(PHP 5 >= 5.1.2)
		return $res;
	}
	function mac512($ent,$key){
		return hash_hmac('sha512', $ent, $key, true);
	}

	
	//////////////////////////////////////////////////////////////////////////////////////////////
	//////////////////////////////////////////////////////////////////////////////////////////////
	////////////	   FUNCIONES PARA LA GENERACIÓN DEL FORMULARIO DE PAGO:			  ////////////
	//////////////////////////////////////////////////////////////////////////////////////////////
	//////////////////////////////////////////////////////////////////////////////////////////////
	
	/******  Obtener Número de pedido ******/
	function getOrder(){
		$numPedido = "";
		if(empty($this->vars_pay['DS_MERCHANT_ORDER'])){
			$numPedido = $this->vars_pay['Ds_Merchant_Order'];
		} else {
			$numPedido = $this->vars_pay['DS_MERCHANT_ORDER'];
		}
		return $numPedido;
	}
	/******  Convertir Array en Objeto JSON ******/
	function arrayToJson(){
		$json = json_encode($this->vars_pay); //(PHP 5 >= 5.2.0)
		return $json;
	}
	function createMerchantParameters(){
		// Se transforma el array de datos en un objeto Json
		$json = $this->arrayToJson();
		// Se codifican los datos Base64
		return $this->encodeBase64($json);
	}
	function createMerchantSignature($key){
		// Se decodifica la clave Base64
		$key = $this->decodeBase64($key);
		// Se genera el parámetro Ds_MerchantParameters
		$ent = $this->createMerchantParameters();
		// Se diversifica la clave con el Número de Pedido
		$key = $this->encrypt_3DES($this->getOrder(), $key);
		// MAC256 del parámetro Ds_MerchantParameters
		$res = $this->mac256($ent, $key);
		// Se codifican los datos Base64
		return $this->encodeBase64($res);
	}
	

	/****** HMAC_SHA512_V2 - Redsys Redirecció v4.1 ******/
	function createMerchantParametersV2(){
		$json = $this->arrayToJson();
		return $this->base64_url_encode_safe($json);
	}
	function createMerchantSignatureV2($key){
		$ent = $this->createMerchantParametersV2();
		$derivedKey = $this->encodeBase64($this->encrypt_AES_V2($this->getOrder(), $key));
		$res = $this->mac512($ent, $derivedKey);
		return $this->base64_url_encode_safe($res);
	}


	//////////////////////////////////////////////////////////////////////////////////////////////
	//////////////////////////////////////////////////////////////////////////////////////////////
	//////////// FUNCIONES PARA LA RECEPCIÓN DE DATOS DE PAGO (Notif, URLOK y URLKO): ////////////
	//////////////////////////////////////////////////////////////////////////////////////////////
	//////////////////////////////////////////////////////////////////////////////////////////////

	/******  Obtener Número de pedido ******/
	function getOrderNotif(){
		$numPedido = "";
		if(empty($this->vars_pay['Ds_Order'])){
			$numPedido = $this->vars_pay['DS_ORDER'];
		} else {
			$numPedido = $this->vars_pay['Ds_Order'];
		}
		return $numPedido;
	}
	function getOrderNotifSOAP($datos){
		$posPedidoIni = strrpos($datos, "<Ds_Order>");
		$tamPedidoIni = strlen("<Ds_Order>");
		$posPedidoFin = strrpos($datos, "</Ds_Order>");
		return substr($datos,$posPedidoIni + $tamPedidoIni,$posPedidoFin - ($posPedidoIni + $tamPedidoIni));
	}
	function getRequestNotifSOAP($datos){
		$posReqIni = strrpos($datos, "<Request");
		$posReqFin = strrpos($datos, "</Request>");
		$tamReqFin = strlen("</Request>");
		return substr($datos,$posReqIni,($posReqFin + $tamReqFin) - $posReqIni);
	}
	function getResponseNotifSOAP($datos){
		$posReqIni = strrpos($datos, "<Response");
		$posReqFin = strrpos($datos, "</Response>");
		$tamReqFin = strlen("</Response>");
		return substr($datos,$posReqIni,($posReqFin + $tamReqFin) - $posReqIni);
	}
	/******  Convertir String en Array ******/
	function stringToArray($datosDecod){
		$this->vars_pay = json_decode($datosDecod, true); //(PHP 5 >= 5.2.0)
	}
	function decodeMerchantParameters($datos){
		// Se decodifican los datos Base64
		$decodec = $this->base64_url_decode($datos);
		return $decodec;	
	}
	function createMerchantSignatureNotif($key, $datos){
		// Se decodifica la clave Base64
		$key = $this->decodeBase64($key);
		// Se decodifican los datos Base64
		$decodec = $this->base64_url_decode($datos);
		// Los datos decodificados se pasan al array de datos
		$this->stringToArray($decodec);
		// Se diversifica la clave con el Número de Pedido
		$key = $this->encrypt_3DES($this->getOrderNotif(), $key);
		// MAC256 del parámetro Ds_Parameters que envía Redsys
		$res = $this->mac256($datos, $key);
		// Se codifican los datos Base64
		return $this->base64_url_encode($res);	
	}
	function createMerchantSignatureNotifV2($key, $datos){
		$decodec = $this->base64_url_decode($datos);
		$this->stringToArray($decodec);
		$derivedKey = $this->encodeBase64($this->encrypt_AES_V2($this->getOrderNotif(), $key));
		$res = $this->mac512($datos, $derivedKey);
		return $this->base64_url_encode_safe($res);
	}
	function createMerchantSignatureNotifForVersion($key, $datos, $version){
		if ($version === 'HMAC_SHA512_V2') {
			return $this->createMerchantSignatureNotifV2($key, $datos);
		}
		if ($version === 'HMAC_SHA256_V1') {
			return $this->createMerchantSignatureNotif($key, $datos);
		}
		throw new RuntimeException('UNSUPPORTED_REDSYS_SIGNATURE_VERSION');
	}
	/******  Notificaciones SOAP ENTRADA ******/
	function createMerchantSignatureNotifSOAPRequest($key, $datos){
		// Se decodifica la clave Base64
		$key = $this->decodeBase64($key);
		// Se obtienen los datos del Request
		$datos = $this->getRequestNotifSOAP($datos);
		// Se diversifica la clave con el Número de Pedido
		$key = $this->encrypt_3DES($this->getOrderNotifSOAP($datos), $key);
		// MAC256 del parámetro Ds_Parameters que envía Redsys
		$res = $this->mac256($datos, $key);
		// Se codifican los datos Base64
		return $this->encodeBase64($res);	
	}
	/******  Notificaciones SOAP SALIDA ******/
	function createMerchantSignatureNotifSOAPResponse($key, $datos, $numPedido){
		// Se decodifica la clave Base64
		$key = $this->decodeBase64($key);
		// Se obtienen los datos del Request
		$datos = $this->getResponseNotifSOAP($datos);
		// Se diversifica la clave con el Número de Pedido
		$key = $this->encrypt_3DES($numPedido, $key);
		// MAC256 del parámetro Ds_Parameters que envía Redsys
		$res = $this->mac256($datos, $key);
		// Se codifican los datos Base64
		return $this->encodeBase64($res);	
	}
}

?>