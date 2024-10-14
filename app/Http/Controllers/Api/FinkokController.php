<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Carbon\Carbon;

class FinkokController extends Controller
{
    private $isCFDI;
    private $isCRP;
    private $isRetencion = false;
    /**
     * Firma electrónica
     * @var string
     */
    private $publicKeyPem;
    private $privateKeyPem;
    private $password;

    # Datos de llave pública
    private $numCertificado;
    private $vigenciaInicio;
    private $vigenciaFinal;

    private $fielAvailable = false;
    private $fielEntries = [];
    private $fiel;

    # XML
    private $xml;
    private $xmlObject;
    private $usernameFinkok;
    private $passwordFinkok;
    private $urlTimbrado;
    private $urlRetenciones;
    private $urlCancelacion;
    private $cadenaOriginal;
    private $env;

    public function __construct()
    {
        $env = $this->env;
        $this->usernameFinkok = $env == 'producction' ? env('FINKOK_USERNAME') : env('FINKOK_USERNAME_TESTEO') ;
        $this->passwordFinkok = $env == 'producction' ? env('FINKOK_PASSWORD') : env('FINKOK_PASSWORD_TESTEO') ;
        $this->urlTimbrado = $env == 'producction' ? env('FINKOK_URL_TIMBRADO') : 'http://demo-facturacion.finkok.com/servicios/soap/stamp.wsdl'; 
        $this->urlRetenciones = $env == 'producction' ? env('FINKOK_URL_RETENCIONES') : 'http://demo-facturacion.finkok.com/servicios/soap/retentions.wsdl'; 
        $this->urlCancelacion = $env == 'producction' ? env('FINKOK_URL_CANCELACION') : 'http://demo-facturacion.finkok.com/servicios/soap/cancel.wsdl'; 
    }

    private function actualizarEnv(){
        $env = $this->env;
        $this->usernameFinkok = $env == 'producction' ? env('FINKOK_USERNAME') : env('FINKOK_USERNAME_TESTEO') ;
        $this->passwordFinkok = $env == 'producction' ? env('FINKOK_PASSWORD') : env('FINKOK_PASSWORD_TESTEO') ;
        $this->urlTimbrado = $env == 'producction' ? env('FINKOK_URL_TIMBRADO') : 'http://demo-facturacion.finkok.com/servicios/soap/stamp.wsdl'; 
        $this->urlRetenciones = $env == 'producction' ? env('FINKOK_URL_RETENCIONES') : 'http://demo-facturacion.finkok.com/servicios/soap/retentions.wsdl'; 
        $this->urlCancelacion = $env == 'producction' ? env('FINKOK_URL_CANCELACION') : 'http://demo-facturacion.finkok.com/servicios/soap/cancel.wsdl'; 
    }

    public function stamp(Request $request)
    {
        $atributos = $request->all();
        $apiKey = env('API_KEY');
        $this->env = $atributos['env'];
        $this->actualizarEnv();
        $userApiKey = $request->header('x-api-key');
        if($apiKey != $userApiKey){
            return response()->json(['validado'=> false, 'data'=> 'ApiKey incorrecta']);
        }
        $this->parseFiel( base64_decode($atributos["certificado"]["cer"]), base64_decode($atributos["certificado"]["key"]), $atributos["certificado"]["password"]);
        $publicKey = $this->publicKeyPem;

        # Elimina encabezados y saltos de linea del certificado público
        $publicKey = str_replace('-----BEGIN CERTIFICATE-----', '', $publicKey);
        $publicKey = str_replace('-----END CERTIFICATE-----', '', $publicKey);
        $publicKey = preg_replace('/\s+/', '', $publicKey);
        $xml = $this->getXml($atributos);
        $xml->attributes()->Certificado = $publicKey;
	    $xml->attributes()->NoCertificado = $this->numCertificado;
        $this->xml = $xml->saveXML();
        $this->xmlObject = $xml;
        $xmlString = $this->sellarXML();
        $isValido = $this->validarXML($xmlString, false);
        if(!$isValido["valido"]){
            return response()->json(['validado'=> $isValido["valido"], 'data'=> $isValido["error"], 'xml'=> $xmlString]);
        }
        $response = $this->_timbrarXML();
        return response()->json([ 'data'=> $response, 'xml'=> $xmlString]);
    }

    public function stampPago(Request $request)
    {
        $atributos = $request->all();
        $apiKey = env('API_KEY');
        $this->env = $atributos['env'];
        $this->actualizarEnv();
        $userApiKey = $request->header('x-api-key');
        if($apiKey != $userApiKey){
            return response()->json(['validado'=> false, 'data'=> 'ApiKey incorrecta']);
        }
        $this->parseFiel( base64_decode($atributos["certificado"]["cer"]), base64_decode($atributos["certificado"]["key"]), $atributos["certificado"]["password"]);
        $publicKey = $this->publicKeyPem;

        # Elimina encabezados y saltos de linea del certificado público
        $publicKey = str_replace('-----BEGIN CERTIFICATE-----', '', $publicKey);
        $publicKey = str_replace('-----END CERTIFICATE-----', '', $publicKey);
        $publicKey = preg_replace('/\s+/', '', $publicKey);
        $xml = $this->getXmlPago($atributos);
        $xml->attributes()->Certificado = $publicKey;
	    $xml->attributes()->NoCertificado = $this->numCertificado;
        $this->xml = $xml->saveXML();
        $this->xmlObject = $xml;
        $xmlString = $this->sellarXML();
        $isValido = $this->validarXML($xmlString, false);
        $response = $this->_timbrarXML();
        return response()->json([ 'data'=> $response, 'xml'=> $xmlString]);
    }

    public function stampPagoMultiMoneda(Request $request)
    {
        $atributos = $request->all();
        $apiKey = env('API_KEY');
        $this->env = $atributos['env'];
        $this->actualizarEnv();
        $userApiKey = $request->header('x-api-key');
        if($apiKey != $userApiKey){
            return response()->json(['validado'=> false, 'data'=> 'ApiKey incorrecta']);
        }
        $this->parseFiel( base64_decode($atributos["certificado"]["cer"]), base64_decode($atributos["certificado"]["key"]), $atributos["certificado"]["password"]);
        $publicKey = $this->publicKeyPem;

        # Elimina encabezados y saltos de linea del certificado público
        $publicKey = str_replace('-----BEGIN CERTIFICATE-----', '', $publicKey);
        $publicKey = str_replace('-----END CERTIFICATE-----', '', $publicKey);
        $publicKey = preg_replace('/\s+/', '', $publicKey);
        $xml = $this->getXmlPagoMultiMoneda($atributos);
        $xml->attributes()->Certificado = $publicKey;
	    $xml->attributes()->NoCertificado = $this->numCertificado;
        $this->xml = $xml->saveXML();
        $this->xmlObject = $xml;
        $xmlString = $this->sellarXML();
        $isValido = $this->validarXML($xmlString, false);
        $response = $this->_timbrarXML();
        return response()->json([ 'data'=> $response, 'xml'=> $xmlString]);
    }

    private function sellarXML()
    {
        $xml = $this->xml;
        $publicKey = $this->publicKeyPem;
        $privateKey = $this->privateKeyPem;
        $privateKey = openssl_get_privatekey($privateKey,$this->password);

        $xdoc = new \DomDocument();
        $xdoc->loadXML($xml);

        # Carga plantilla según el tipo de documento
        $XSL = new \DOMDocument();
        $XSL->load(base_path('resources/facturacion/plantillas/xslt33/cadenaoriginal.xslt'));

        # Genera cadena original
        $proc = new \XSLTProcessor;
        $proc->importStyleSheet($XSL);
        $cadenaOriginal = $proc->transformToXML($xdoc);

        # Genera firma digital para la cadena original usando la llave privada
        openssl_sign($cadenaOriginal, $binarySign, $privateKey, OPENSSL_ALGO_SHA256);
        openssl_free_key($privateKey);
       
        
        # Encode sello
        $sello = base64_encode($binarySign);

        # Coloca atributo sello al documento xml
        $c = $xdoc->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/4', 'Comprobante')->item(0);
        $c->setAttribute('Sello', $sello);

        # Almacena xlm sellado
        $this->xml = $xdoc->saveXML();

        # Almacena la cadena original
        $this->cadenaOriginal = $cadenaOriginal;

        return $this->xml;
    }

    private function _timbrarXML()
    {
        $xml = $this->xml;
        # Consuming the stamp service
        if ($this->isCFDI || $this->isCRP) {
            $url = $this->urlTimbrado;
        } elseif ($this->isRetencion) {
            $url = $this->urlRetenciones;
        }
        
        $pacUsername = $this->usernameFinkok;
        $pacPassword = $this->passwordFinkok;
        $client = new \SoapClient($url);

        # Setea parametros para consumir el stamp service
        $params = [
          'xml' => $xml,
          'username' => $pacUsername,
          'password' => $pacPassword
        ];

        # Llamada al webservice
        $response = $client->__soapCall('stamp', [$params]);
        //dd('response', $response, 'xml', $xml);

        # Cast object to array
        $response = json_decode(json_encode($response), true);

        # Lanza excepción sino tiene el nodo stampResult
        if (!Arr::has($response, ['stampResult'])) {
            throw new FacturacionException("No se recibió una respuesta correcta del proveedor");
        }
        # Si hubo incidencias
        if (!empty($response['stampResult']['Incidencias'])) {
            foreach ($response['stampResult']['Incidencias'] as $key => $incidencia) {
                return "Error Timbrado: ({$incidencia['CodigoError']}) {$incidencia['MensajeIncidencia']}";
            }
        } else if (!empty($response['stampResult']['xml'])) {

            # Retorna xml timbrado
            return [
                'validado' => true,
                'xml' => $response['stampResult']['xml'],
                'fecha' => $response['stampResult']['Fecha'],
                'uuid' => $response['stampResult']['UUID'],
                'msg' => $response['stampResult']['CodEstatus'],
                'selloSat' => $response['stampResult']['SatSeal'],
                'cadena_original' => $this->cadenaOriginal
            ];

        } else {
            # Si no hay incidencias y no retorno tampoco el xml, un error ocurrio con el webservice
            throw new FacturacionException("Ha ocurrido un error al timbrar xml con el proveedor");
        }
    }

    public function cancel(Request $request)
    {
        $atributos = $request->all();
        $apiKey = env('API_KEY');
        $this->env = $atributos['env'];
        $this->actualizarEnv();
        $userApiKey = $request->header('x-api-key');
        if($apiKey != $userApiKey){
            return response()->json(['validado'=> false, 'data'=> 'ApiKey incorrecta']);
        }
        $this->parseFiel( base64_decode($atributos["certificado"]["cer"]), base64_decode($atributos["certificado"]["key"]), $atributos["certificado"]["password"]);
        $motivosValidos = ['01','02','03','04'];
	    if (!in_array($atributos["motivo"], $motivosValidos)) {
            return response()->json(['validado'=> false, 'data'=> "Motivo de cancelación no válido."]);
        }
        $response = $this->cancelar([$atributos["uuid"]],$atributos["rfc_emisor"],$atributos["motivo"], '');
        return response()->json(['validado'=> true, 'data'=> $response]);
    }

    public function cancelar($uuids, $rfcEmisor, $motivo_cancelacion, $UUID_relacionado)
    {

        # Valida información
        if ((! is_array($uuids) && ! is_string($uuids)) || empty($rfcEmisor)) {
            throw new PayloadException(
                'Error de validación',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'Los datos recibidos no son procesables');
        }


        # Si recibe un UUID individual, lo envuelve en un arreglo
        if (is_string($uuids)){
            $uuids = [$uuids];
        }

        # Cancelación de CFDI
        $response = $this->_cancelarCFDI($uuids, $rfcEmisor, $motivo_cancelacion, $UUID_relacionado);

        return $response;
    }

    private function _cancelarCFDI($uuids = [], $rfcEmisor, $motivo_cancelacion, $UUID_relacionado)
    {
        if (count($uuids) > 1) {
            throw new FacturacionException('Sin soporte actual para cancelar varias facturas.');
        }
        $pacUsername = $this->usernameFinkok;
        $pacPassword = $this->passwordFinkok;
        $url = $this->urlCancelacion;
        $client = new \SoapClient($url);

        // Armamos como se debe el array de cancelación:
        $uuids = array('UUID' => $uuids[0], 'Motivo' => $motivo_cancelacion, 'FolioSustitucion' => '');
        $uuid_ar = array('UUID' => $uuids);

        # Setea parametros para consumir el stamp service
        $privateKey = $this->privateKeyPem;

        $privateKey = openssl_get_privatekey($privateKey,$this->password);
        openssl_pkey_export($privateKey, $rawKey);
        $params = [
          'UUIDS' => $uuid_ar,
          'username' => $pacUsername,
          'password' => $pacPassword,
          'taxpayer_id' => $rfcEmisor,
          'cer' => $this->publicKeyPem,
          'key' =>  $rawKey
        ];
        # Llamada al webservice
        $response = $client->__soapCall('cancel', [$params]);
        # Cast object to array
        $response = json_decode(json_encode($response), true);

        # Lanza excepción sino tiene el nodo stampResult
        if (!Arr::has($response, ['cancelResult'])) {
            throw new FacturacionException("No se recibió una respuesta correcta del proveedor");
        }

        # Si hubo incidencias
        if (!empty($response['cancelResult']['Folios']['Folio'])) {

            # Retorna acuse de cancelación
            return [
                'folios' => $response['cancelResult']['Folios']['Folio'],
                'acuse' => $response['cancelResult']['Acuse'],
                'fecha' => $response['cancelResult']['Fecha'],
                'motivo_cancelacion' => $motivo_cancelacion,
                'UUID_relacionado' => $UUID_relacionado
            ];

        } else if (! empty($response['cancelResult']['CodEstatus'])) {
            return "Error Timbrado: ({$response['cancelResult']['CodEstatus']})";
            
        } else {
            # Si no hay incidencias y no retorno tampoco el xml, un error ocurrio con el webservice
            throw new FacturacionException("Ha ocurrido un error al cancelar CFDI con el proveedor");
        }
    }

    protected function parseFiel($publicKey, $privateKey, $privatePassword)
    {
        if (empty($publicKey) || empty($privateKey) || empty($privatePassword)) {
            return false;
        }

        # Obtiene contenido del certificado público
        $cerContent = $publicKey;
        $cerContent = $this->der2pem($cerContent, false);

        # Guarda contenido PEM de llave pública
        $this->publicKeyPem = $cerContent;

        # Parsea certificado público
        $pubKey = openssl_x509_parse($cerContent, false);

        # Si el parseo se hizo correctamente
        if ($pubKey !== false) {

            # Si el contenido parseado tiene la siguiente propiedades
            if (Arr::has($pubKey, ['serialNumber', 'validFrom_time_t', 'validTo_time_t'])) {

                # Obtiene datos relevantes del certificado$pubKey = $request->input('pubKey');

                $serialNumberHex = Arr::get($pubKey, 'serialNumber');
                if (strpos($serialNumberHex, '0x') === 0) {
                    $serialNumberHex = substr($serialNumberHex, 2);
                }
                $serialNumber = pack("H*", $serialNumberHex);

                $vigenciaInicio = date('Y-m-d H:i:s', $pubKey['validFrom_time_t']);
                $vigenciaFinal = date('Y-m-d H:i:s', $pubKey['validTo_time_t']);

                $nowDate = date("Y-m-d H:i:s");

                # Si la vigencia del certificado aún es válida
                if ($nowDate >= $vigenciaInicio && $nowDate <= $vigenciaFinal) {

                    # Habilita bandera para indicar que si hay certificados FIEL disponibles
                    $this->fielAvailable = true;

                    $this->numCertificado = $serialNumber;
                    // $this->numCertificado = '30001000000400002434';
                    //$this->numCertificado = '30001000000500003416';
                    $this->vigenciaInicio = $vigenciaInicio;
                    $this->vigenciaFinal = $vigenciaFinal;

                } else {
                    throw new FacturacionException('La vigencia de los certificados ha expirado.');
                }

            }

        }

        # Obtiene contenido del certificado privado
        $keyContent = $privateKey;
        $keyContent = $this->der2pem($keyContent, true);

        # Guarda contenido PEM de llave privada
        $this->privateKeyPem = $keyContent;

        # Guarda contraseña de llave privada
        $this->password = $privatePassword;
    }

    public function der2pem($der, $isPrivateKey = false)
    {

        $BEGIN_MARKER = $isPrivateKey ? '-----BEGIN ENCRYPTED PRIVATE KEY-----' : '-----BEGIN CERTIFICATE-----';
        $END_MARKER = $isPrivateKey ? '-----END ENCRYPTED PRIVATE KEY-----' : '-----END CERTIFICATE-----';

        $value = base64_encode($der);

        $pem = $BEGIN_MARKER . "\n";
        $pem .= chunk_split($value, 64, "\n");
        $pem .= $END_MARKER . "\n";

        return $pem;
    }

    private function getXml($atributos)
    {   
        date_default_timezone_set('America/Guatemala');
        $now = Carbon::now();
        $date = $now->format('Y-m-d');
        $hora = $now->toTimeString();
        $fecha_actual = $date.'T'.$hora;

        // Crea una nueva instancia XML
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><cfdi:Comprobante></cfdi:Comprobante>', LIBXML_NOERROR, false, 'cfdi', true);

        # Atributos requeridos
        $xml->addAttribute('xmlns:xmlns:cfdi', 'http://www.sat.gob.mx/cfd/4');
        $xml->addAttribute('xmlns:xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $xml->addAttribute('xmlns:xsi:schemaLocation', 'http://www.sat.gob.mx/cfd/4 http://www.sat.gob.mx/sitio_internet/cfd/4/cfdv40.xsd http://www.sat.gob.mx/implocal http://www.sat.gob.mx/sitio_internet/cfd/implocal/implocal.xsd');
        $xml->addAttribute('xmlns:xmlns:implocal', 'http://www.sat.gob.mx/implocal');
        $xml->addAttribute('Version','4.0');
        $xml->addAttribute('Fecha', $fecha_actual);
        $xml->addAttribute('Folio', $atributos["certificado"]['folio']);
        $xml->addAttribute('FormaPago', $atributos["certificado"]['formaPago']);
        $xml->addAttribute('Moneda', $atributos["certificado"]['claveMoneda']);
        $xml->addAttribute('SubTotal', $this->formatCurrency($atributos["certificado"]['subTotalFactura'],2));
        $xml->addAttribute('Total', $this->formatCurrency($atributos["certificado"]['totalFactura'],2));
        $xml->addAttribute('TipoDeComprobante', $atributos["certificado"]['tipoDeComprobante']);
        $xml->addAttribute('MetodoPago', $atributos["certificado"]['metodoPago']);
        $xml->addAttribute('LugarExpedicion', $atributos["certificado"]['lugarExpedicion']);
        $xml->addAttribute('Certificado', '');
        $xml->addAttribute('NoCertificado', '');
        $xml->addAttribute('Exportacion', '01');

        # Añade serie (si está definida)
        if (Arr::has($atributos["certificado"], ['serie']) && !is_null($atributos["certificado"]['serie'])){
            $xml->addAttribute('Serie', $atributos["certificado"]['serie']);
        }

        # Atributos opcionales
        if(Arr::has($atributos["certificado"], ['condicionesDePago'])){
            $xml->addAttribute('CondicionesDePago', $atributos["certificado"]['condicionesDePago']);
        }
        if(Arr::has($atributos["certificado"], ['descuentoFactura']) && $atributos["certificado"]['descuentoFactura'] > 0){
            $xml->addAttribute('Descuento', $this->formatCurrency($atributos["certificado"]['descuentoFactura'],2));
        }
        if(Arr::has($atributos["certificado"], ['tipoCambio'])){
            $xml->addAttribute('TipoCambio', $atributos["certificado"]['tipoCambio']);
        }

        # CfdiRelacionados, atributos opcionales
        if(Arr::has($atributos["certificado"], ['cfdiRelacionados'])){
            foreach ($atributos['certificado']['cfdiRelacionados'] as $cfdiRel){
                $cfdiRelacionados = $xml->addChild('xmlns:cfdi:CfdiRelacionados');
                $cfdiRelacionados->addAttribute('TipoRelacion', $cfdiRel['tipoRelacion']);
                $cfdiRelacionado = $cfdiRelacionados->addChild('xmlns:cfdi:CfdiRelacionado');
                $cfdiRelacionado->addAttribute('UUID', $cfdiRel['uuid']);
            }
        }

        # Emisor, atributos requeridos
        $emisor = $xml->addChild('xmlns:cfdi:Emisor');
        $emisor->addAttribute('Rfc', $atributos['emisor']['rfc']);
        $emisor->addAttribute('Nombre', $atributos['emisor']['nombre']);
        $emisor->addAttribute('RegimenFiscal', $atributos['emisor']['regimenFiscal']);

        # Receptor, atributos requeridos
        $receptor = $xml->addChild('xmlns:cfdi:Receptor');
        $receptor->addAttribute('Rfc', $atributos['receptor']['rfc']);
        $receptor->addAttribute('Nombre', $atributos['receptor']['nombre']);
        $receptor->addAttribute('UsoCFDI', $atributos['receptor']['usoCFDI']);
        $receptor->addAttribute('DomicilioFiscalReceptor',$atributos['receptor']['domicilioFiscal']);
        $receptor->addAttribute('RegimenFiscalReceptor',$atributos['receptor']['regimenFiscal']);
        
        
        
    
        # Conceptos, atributos requeridos
        $conceptos = $xml->addChild('xmlns:cfdi:Conceptos');
        foreach ($atributos['conceptos'] as $concepto) {
            # Atributos requeridos
            $c = $conceptos->addChild('xmlns:cfdi:Concepto');
            $c->addAttribute('ClaveProdServ', $concepto['ClaveProdServ']);
            $c->addAttribute('ClaveUnidad', $concepto['ClaveUnidad']);
            $c->addAttribute('Cantidad', $concepto['Cantidad']);
            $c->addAttribute('Unidad', $concepto['Unidad']);
            $c->addAttribute('Descripcion', $concepto['Descripcion']);
            $c->addAttribute('ValorUnitario', $concepto['ValorUnitario']);
            $c->addAttribute('Importe', $this->formatCurrency($concepto['Importe'],2));
            $c->addAttribute('ObjetoImp',$concepto['ObjetoImp']);



            # Atributos opcionales
            if(Arr::has($concepto, ['NoIdentificacion'])){
                $c->addAttribute('NoIdentificacion', $concepto['NoIdentificacion']);
            }
            if(Arr::has($concepto, ['Descuento']) && $concepto['Descuento'] > 0){
                $c->addAttribute('Descuento', $this->formatCurrency($concepto['Descuento'],2));
            }

            # Valida si hay impuestos trasladados
            if(Arr::has($concepto, ['impuestos']) && count($concepto['impuestos']) == 1){
                # Impuestos trasladados por concepto
                $impuestos = $c->addChild('xmlns:cfdi:Impuestos');

                $impuesto_trasladado = $impuestos->addChild('xmlns:cfdi:Traslados');
                $traslado = $impuesto_trasladado->addChild('xmlns:cfdi:Traslado');
                $impuestosData = $concepto['impuestos'][0];
                $traslado->addAttribute('Base', $this->formatCurrency($impuestosData['base'],2));
                $traslado->addAttribute('Impuesto', $impuestosData['impuesto']);
                $traslado->addAttribute('TipoFactor', $impuestosData['tipoFactor']);
                $traslado->addAttribute('TasaOCuota', $impuestosData['tasaOCuota']);
                $traslado->addAttribute('Importe', $this->formatCurrency($impuestosData['importe'],2));
            }

        }


        if(Arr::has($atributos, ['impuestos'])){
            $impuestosGenerales = $xml->addChild('xmlns:cfdi:Impuestos');
            $impuesto_trasladado = $impuestosGenerales->addChild('xmlns:cfdi:Traslados');
            $impuestosTotalesData = $atributos['impuestos']['traslados'];
            foreach ($impuestosTotalesData as $transladoData){
                $traslado = $impuesto_trasladado->addChild('xmlns:cfdi:Traslado');
                $traslado->addAttribute('Base', $this->formatCurrency($transladoData['base'],2));
                $traslado->addAttribute('Impuesto', $transladoData['impuesto']);
                $traslado->addAttribute('TipoFactor', $transladoData['tipoFactor']);
                $traslado->addAttribute('TasaOCuota', $transladoData['tasaOCuota']);
                $traslado->addAttribute('Importe', $this->formatCurrency($transladoData['importe'],2));
            }
            $impuestosGenerales->addAttribute('TotalImpuestosTrasladados', $this->formatCurrency($atributos['impuestos']['totalImpuestosTrasladados'],2));
        }

        $this->isCFDI = true;
        return $xml;
    }
    
    private function getXmlPago($atributos)
    {   
        date_default_timezone_set('America/Guatemala');
        $now = Carbon::now();
        $date = $now->format('Y-m-d');
        $hora = $now->toTimeString();
        $fecha_actual = $date.'T'.$hora;

        // Crea una nueva instancia XML
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><cfdi:Comprobante></cfdi:Comprobante>', LIBXML_NOERROR, false, 'cfdi', true);

        $folioData = explode("-", $atributos["certificado"]['folio']);
        # Atributos requeridos
        $xml->addAttribute('xmlns:xsi:schemaLocation',  'http://www.sat.gob.mx/cfd/4 http://www.sat.gob.mx/sitio_internet/cfd/4/cfdv40.xsd http://www.sat.gob.mx/Pagos20 http://www.sat.gob.mx/sitio_internet/cfd/Pagos/Pagos20.xsd');
        
        $xml->addAttribute('Version','4.0');
        $xml->addAttribute('Serie', $folioData[0]);
        $xml->addAttribute('Folio', $folioData[1]);
        $xml->addAttribute('Fecha', $fecha_actual);
        $xml->addAttribute('Sello', '');
        $xml->addAttribute('NoCertificado', '');
        $xml->addAttribute('Certificado', '');
        $xml->addAttribute('SubTotal', 0);
        $xml->addAttribute('Moneda', 'XXX');
        $xml->addAttribute('Total', 0);
        $xml->addAttribute('TipoDeComprobante', $atributos["certificado"]['tipoDeComprobante']);
        $xml->addAttribute('Exportacion', '01');
        $xml->addAttribute('LugarExpedicion', $atributos["certificado"]['lugarExpedicion']);

        $xml->addAttribute('xmlns:xmlns:cfdi',          'http://www.sat.gob.mx/cfd/4');
        $xml->addAttribute('xmlns:xmlns:pago20',        'http://www.sat.gob.mx/Pagos20');
        $xml->addAttribute('xmlns:xmlns:xsi',           'http://www.w3.org/2001/XMLSchema-instance');

        $decimales = 6;
        $decimalesTotales = 2;

        # Emisor, atributos requeridos
        $emisor = $xml->addChild('xmlns:cfdi:Emisor');
        $emisor->addAttribute('Rfc', $atributos['emisor']['rfc']);
        $emisor->addAttribute('Nombre', $atributos['emisor']['nombre']);
        $emisor->addAttribute('RegimenFiscal', $atributos['emisor']['regimenFiscal']);

        # Receptor, atributos requeridos
        $receptor = $xml->addChild('xmlns:cfdi:Receptor');
        $receptor->addAttribute('Rfc', $atributos['receptor']['rfc']);
        $receptor->addAttribute('Nombre', $atributos['receptor']['nombre']);
        $receptor->addAttribute('UsoCFDI', "CP01");
        $receptor->addAttribute('DomicilioFiscalReceptor',$atributos['receptor']['domicilioFiscal']);
        $receptor->addAttribute('RegimenFiscalReceptor',$atributos['receptor']['regimenFiscal']);
        
        
        # Añade nodo de Conceptos con el concepto general de pago
        $conceptos = $xml->addChild('xmlns:cfdi:Conceptos');
        $conceptoPago = $conceptos->addChild('xmlns:cfdi:Concepto');

        $conceptoPago->addAttribute('ClaveProdServ', '84111506');
        $conceptoPago->addAttribute('ClaveUnidad', 'ACT');
        $conceptoPago->addAttribute('Cantidad', 1);
        $conceptoPago->addAttribute('Descripcion', 'Pago');
        $conceptoPago->addAttribute('ValorUnitario', 0);
        $conceptoPago->addAttribute('Importe', 0);
        $conceptoPago->addAttribute('ObjetoImp', '01');

        # Añade node Complemento
        $complemento = $xml->addChild('xmlns:cfdi:Complemento');
        # Creamos un solo nodo de pago para realizar un solo pago de todos los documentos relacionados. 
        $pagos = $complemento->addChild('xmlns:pago20:Pagos');
        # Se añade atributo Version al nodo pago20:Pagos 
        $pagos->addAttribute('Version', '2.0');
        # Se añade el nodo pago20:Totales
        $totales = $pagos->addChild('xmlns:pago20:Totales');
        $totalesData = $atributos['pagos']['totales'];

        $pagoData = $atributos['pagos']['pago'];
        # Se valida y se agregan los atributos al nodo pago20:Totales
        if (Arr::has($totalesData, ['TotalTrasladosBaseIVA16']) && !is_null($totalesData["TotalTrasladosBaseIVA16"]) && $totalesData["TotalTrasladosBaseIVA16"] > 0){
            $totales->addAttribute('TotalTrasladosBaseIVA16', $this->formatCurrency($totalesData["TotalTrasladosBaseIVA16"],$decimalesTotales));
            $totales->addAttribute('TotalTrasladosImpuestoIVA16', $this->formatCurrency($totalesData["TotalTrasladosImpuestoIVA16"],$decimalesTotales));
        }
        if (Arr::has($totalesData, ['TotalTrasladosBaseIVA0']) && !is_null($totalesData["TotalTrasladosBaseIVA0"]) && $totalesData["TotalTrasladosBaseIVA0"] > 0){
            $totales->addAttribute('TotalTrasladosBaseIVA0', $this->formatCurrency($totalesData["TotalTrasladosBaseIVA0"],$decimalesTotales));
            $totales->addAttribute('TotalTrasladosImpuestoIVA0', "0.0");
        }
        $totales->addAttribute('MontoTotalPagos', $this->formatCurrency($totalesData["MontoTotalPagos"],$decimalesTotales));

        # Se añade el nodo pago20:Pago
        $pago = $pagos->addChild('xmlns:pago20:Pago');

        $pago->addAttribute('FechaPago', $pagoData['FechaPago']);
        # Se añaden los atributos del pago20:Pago
        $pago->addAttribute('FormaDePagoP', $pagoData['FormaDePagoP']);
        $pago->addAttribute('MonedaP', $pagoData['MonedaP']);
        $pago->addAttribute('TipoCambioP', $pagoData['MonedaP'] == "MXN" ? 1 : $this->formatCurrency($pagoData["TipoCambioP"],$decimales));
        $pago->addAttribute('Monto', $this->formatCurrency($pagoData["Monto"],$decimalesTotales));
        
        $equivalenciaData = 1;
        # Se añaden los nodos de documentos relacionados
        $documentosRelacionados = $atributos['pagos']['DoctoRelacionados'];
        for ($i=0; $i < count($documentosRelacionados); $i++) { 
            $documentoRData = $documentosRelacionados[$i];
            $equivalenciaData = $documentoRData["EquivalenciaDR"];
            $documentoR = $pago->addChild('xmlns:pago20:DoctoRelacionado');
            # Se añaden los atributos del pago20:DoctoRelacionado
            $documentoR->addAttribute('IdDocumento', $documentoRData['IdDocumento']);
            $documentoR->addAttribute('MonedaDR', $documentoRData['MonedaDR']);
            $documentoR->addAttribute('ObjetoImpDR', $documentoRData['ObjetoImpDR']);
            $documentoR->addAttribute('NumParcialidad', "".$documentoRData['NumParcialidad']);
            $documentoR->addAttribute('EquivalenciaDR', $documentoRData["EquivalenciaDR"] == 1 ? "1" : $this->formatCurrency($documentoRData["EquivalenciaDR"],10));
            $documentoR->addAttribute('ImpSaldoAnt', $this->formatCurrency($documentoRData["ImpSaldoAnt"],$decimalesTotales));
            $documentoR->addAttribute('ImpPagado', $this->formatCurrency($documentoRData["ImpPagado"],$decimalesTotales));
            $documentoR->addAttribute('ImpSaldoInsoluto', $this->formatCurrency($documentoRData["ImpSaldoInsoluto"],$decimalesTotales));
            # Se añade el nodo de pago20:ImpuestosDR
            $impuestoR = $documentoR->addChild('xmlns:pago20:ImpuestosDR');
            # Se añade el nodo de pago20:TrasladosDR
            $trasladosR = $impuestoR->addChild('xmlns:pago20:TrasladosDR');
            # Se añaden los nodos de pago20:TrasladoDR
            $trasladosRData = $documentoRData['ImpuestosDR'];
            for ($j=0; $j < count($trasladosRData); $j++) { 
                $trasladoRData = $trasladosRData[$j];
                $trasladoR = $trasladosR->addChild('xmlns:pago20:TrasladoDR');
                $trasladoR->addAttribute('BaseDR', $this->formatCurrency($trasladoRData["BaseDR"],$decimalesTotales));
                $trasladoR->addAttribute('ImpuestoDR', $trasladoRData['ImpuestoDR']);
                $trasladoR->addAttribute('TipoFactorDR', $trasladoRData['TipoFactorDR']);
                $trasladoR->addAttribute('TasaOCuotaDR', $trasladoRData['TasaOCuotaDR']);
                $trasladoR->addAttribute('ImporteDR', $trasladoRData["ImporteDR"] == 0 ? "0" : $this->formatCurrency($trasladoRData["ImporteDR"],$decimalesTotales));
            }
        }
        # Se añaden los nodos ImpuestosP
        $impuestosPagoData = $atributos['pagos']['ImpuestosP'];
        # Se añade el nodo de pago20:ImpuestosP
        $impuestoP = $pago->addChild('xmlns:pago20:ImpuestosP');
        # Se añade el nodo de pago20:TrasladosP
        $trasladosP = $impuestoP->addChild('xmlns:pago20:TrasladosP');
        for ($i=0; $i < count($impuestosPagoData); $i++) { 
            $impuestoPagoData = $impuestosPagoData[$i];
            # Se añade el nodo de pago20:TrasladoP
            $trasladoP = $trasladosP->addChild('xmlns:pago20:TrasladoP');
            $trasladoP->addAttribute('BaseP', $this->formatCurrency(($impuestoPagoData["BaseP"]),$decimalesTotales));
            $trasladoP->addAttribute('ImpuestoP', $impuestoPagoData['ImpuestoP']);
            $trasladoP->addAttribute('TipoFactorP', $impuestoPagoData['TipoFactorP']);
            $trasladoP->addAttribute('TasaOCuotaP', $impuestoPagoData['TasaOCuotaP']);
            $trasladoP->addAttribute('ImporteP', $this->formatCurrency(($impuestoPagoData["ImporteP"]),$decimalesTotales));
        }
        $this->isCFDI = true;
        return $xml;
    }
    
    private function getXmlPagoMultiMoneda($atributos)
    {   
        date_default_timezone_set('America/Guatemala');
        $now = Carbon::now();
        $date = $now->format('Y-m-d');
        $hora = $now->toTimeString();
        $fecha_actual = $date.'T'.$hora;

        // Crea una nueva instancia XML
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><cfdi:Comprobante></cfdi:Comprobante>', LIBXML_NOERROR, false, 'cfdi', true);

        $folioData = explode("-", $atributos["certificado"]['folio']);
        # Atributos requeridos
        $xml->addAttribute('xmlns:xsi:schemaLocation',  'http://www.sat.gob.mx/cfd/4 http://www.sat.gob.mx/sitio_internet/cfd/4/cfdv40.xsd http://www.sat.gob.mx/Pagos20 http://www.sat.gob.mx/sitio_internet/cfd/Pagos/Pagos20.xsd');
        
        $xml->addAttribute('Version','4.0');
        $xml->addAttribute('Serie', $folioData[0]);
        $xml->addAttribute('Folio', $folioData[1]);
        $xml->addAttribute('Fecha', $fecha_actual);
        $xml->addAttribute('Sello', '');
        $xml->addAttribute('NoCertificado', '');
        $xml->addAttribute('Certificado', '');
        $xml->addAttribute('SubTotal', 0);
        $xml->addAttribute('Moneda', 'XXX');
        $xml->addAttribute('Total', 0);
        $xml->addAttribute('TipoDeComprobante', $atributos["certificado"]['tipoDeComprobante']);
        $xml->addAttribute('Exportacion', '01');
        $xml->addAttribute('LugarExpedicion', $atributos["certificado"]['lugarExpedicion']);

        $xml->addAttribute('xmlns:xmlns:cfdi',          'http://www.sat.gob.mx/cfd/4');
        $xml->addAttribute('xmlns:xmlns:pago20',        'http://www.sat.gob.mx/Pagos20');
        $xml->addAttribute('xmlns:xmlns:xsi',           'http://www.w3.org/2001/XMLSchema-instance');

        $decimales = 6;
        $decimalesTotales = 2;

        # Emisor, atributos requeridos
        $emisor = $xml->addChild('xmlns:cfdi:Emisor');
        $emisor->addAttribute('Rfc', $atributos['emisor']['rfc']);
        $emisor->addAttribute('Nombre', $atributos['emisor']['nombre']);
        $emisor->addAttribute('RegimenFiscal', $atributos['emisor']['regimenFiscal']);

        # Receptor, atributos requeridos
        $receptor = $xml->addChild('xmlns:cfdi:Receptor');
        $receptor->addAttribute('Rfc', $atributos['receptor']['rfc']);
        $receptor->addAttribute('Nombre', $atributos['receptor']['nombre']);
        $receptor->addAttribute('UsoCFDI', "CP01");
        $receptor->addAttribute('DomicilioFiscalReceptor',$atributos['receptor']['domicilioFiscal']);
        $receptor->addAttribute('RegimenFiscalReceptor',$atributos['receptor']['regimenFiscal']);
        
        
        # Añade nodo de Conceptos con el concepto general de pago
        $conceptos = $xml->addChild('xmlns:cfdi:Conceptos');
        $conceptoPago = $conceptos->addChild('xmlns:cfdi:Concepto');

        $conceptoPago->addAttribute('ClaveProdServ', '84111506');
        $conceptoPago->addAttribute('ClaveUnidad', 'ACT');
        $conceptoPago->addAttribute('Cantidad', 1);
        $conceptoPago->addAttribute('Descripcion', 'Pago');
        $conceptoPago->addAttribute('ValorUnitario', 0);
        $conceptoPago->addAttribute('Importe', 0);
        $conceptoPago->addAttribute('ObjetoImp', '01');

        # Añade node Complemento
        $complemento = $xml->addChild('xmlns:cfdi:Complemento');
        # Creamos un solo nodo de pago para realizar un solo pago de todos los documentos relacionados. 
        $pagos = $complemento->addChild('xmlns:pago20:Pagos');
        # Se añade atributo Version al nodo pago20:Pagos 
        $pagos->addAttribute('Version', '2.0');
        # Se añade el nodo pago20:Totales
        $totales = $pagos->addChild('xmlns:pago20:Totales');
        $totalesData = $atributos['pagos']['totales'];

        $pagoData = $atributos['pagos']['pago'];
        # Se valida y se agregan los atributos al nodo pago20:Totales
        if (Arr::has($totalesData, ['TotalTrasladosBaseIVA16']) && !is_null($totalesData["TotalTrasladosBaseIVA16"]) && $totalesData["TotalTrasladosBaseIVA16"] > 0){
            $totales->addAttribute('TotalTrasladosBaseIVA16', $this->formatCurrency($totalesData["TotalTrasladosBaseIVA16"],$decimalesTotales));
            $totales->addAttribute('TotalTrasladosImpuestoIVA16', $this->formatCurrency($totalesData["TotalTrasladosImpuestoIVA16"],$decimalesTotales));
        }
        if (Arr::has($totalesData, ['TotalTrasladosBaseIVA0']) && !is_null($totalesData["TotalTrasladosBaseIVA0"]) && $totalesData["TotalTrasladosBaseIVA0"] > 0){
            $totales->addAttribute('TotalTrasladosBaseIVA0', $this->formatCurrency($totalesData["TotalTrasladosBaseIVA0"],$decimalesTotales));
            $totales->addAttribute('TotalTrasladosImpuestoIVA0', "0.0");
        }
        $totales->addAttribute('MontoTotalPagos', $this->formatCurrency($totalesData["MontoTotalPagos"],$decimalesTotales));

        foreach ($pagoData as $moneda => $detallePago){
            # Se añade el nodo pago20:Pago
            $pago = $pagos->addChild('xmlns:pago20:Pago');

            $pago->addAttribute('FechaPago', $detallePago['FechaPago']);
            # Se añaden los atributos del pago20:Pago
            $pago->addAttribute('FormaDePagoP', $detallePago['FormaDePagoP']);
            $pago->addAttribute('MonedaP', $detallePago['MonedaP']);
            $pago->addAttribute('TipoCambioP', $detallePago['MonedaP'] == "MXN" ? 1 : $this->formatCurrency($detallePago["TipoCambioP"],$decimales));
            $pago->addAttribute('Monto', $this->formatCurrency($detallePago["Monto"],$decimalesTotales));
            
            $equivalenciaData = 1;
            # Se añaden los nodos de documentos relacionados
            $documentosRelacionados = $detallePago['DoctoRelacionados'];
            for ($i=0; $i < count($documentosRelacionados); $i++) { 
                $documentoRData = $documentosRelacionados[$i];
                $equivalenciaData = $documentoRData["EquivalenciaDR"];
                $documentoR = $pago->addChild('xmlns:pago20:DoctoRelacionado');
                # Se añaden los atributos del pago20:DoctoRelacionado
                $documentoR->addAttribute('IdDocumento', $documentoRData['IdDocumento']);
                $documentoR->addAttribute('MonedaDR', $documentoRData['MonedaDR']);
                $documentoR->addAttribute('ObjetoImpDR', $documentoRData['ObjetoImpDR']);
                $documentoR->addAttribute('NumParcialidad', "".$documentoRData['NumParcialidad']);
                $documentoR->addAttribute('EquivalenciaDR', $documentoRData["EquivalenciaDR"] == 1 ? "1" : $this->formatCurrency($documentoRData["EquivalenciaDR"],10));
                $documentoR->addAttribute('ImpSaldoAnt', $this->formatCurrency($documentoRData["ImpSaldoAnt"],$decimalesTotales));
                $documentoR->addAttribute('ImpPagado', $this->formatCurrency($documentoRData["ImpPagado"],$decimalesTotales));
                $documentoR->addAttribute('ImpSaldoInsoluto', $this->formatCurrency($documentoRData["ImpSaldoInsoluto"],$decimalesTotales));
                # Se añade el nodo de pago20:ImpuestosDR
                $impuestoR = $documentoR->addChild('xmlns:pago20:ImpuestosDR');
                # Se añade el nodo de pago20:TrasladosDR
                $trasladosR = $impuestoR->addChild('xmlns:pago20:TrasladosDR');
                # Se añaden los nodos de pago20:TrasladoDR
                $trasladosRData = $documentoRData['ImpuestosDR'];
                for ($j=0; $j < count($trasladosRData); $j++) { 
                    $trasladoRData = $trasladosRData[$j];
                    $trasladoR = $trasladosR->addChild('xmlns:pago20:TrasladoDR');
                    $trasladoR->addAttribute('BaseDR', $this->formatCurrency($trasladoRData["BaseDR"],$decimalesTotales));
                    $trasladoR->addAttribute('ImpuestoDR', $trasladoRData['ImpuestoDR']);
                    $trasladoR->addAttribute('TipoFactorDR', $trasladoRData['TipoFactorDR']);
                    $trasladoR->addAttribute('TasaOCuotaDR', $trasladoRData['TasaOCuotaDR']);
                    $trasladoR->addAttribute('ImporteDR', $trasladoRData["ImporteDR"] == 0 ? "0" : $this->formatCurrency($trasladoRData["ImporteDR"],$decimalesTotales));
                }
            }
            # Se añaden los nodos ImpuestosP
            $impuestosPagoData = $detallePago['ImpuestosP'];
            # Se añade el nodo de pago20:ImpuestosP
            $impuestoP = $pago->addChild('xmlns:pago20:ImpuestosP');
            # Se añade el nodo de pago20:TrasladosP
            $trasladosP = $impuestoP->addChild('xmlns:pago20:TrasladosP');
            for ($i=0; $i < count($impuestosPagoData); $i++) { 
                $impuestoPagoData = $impuestosPagoData[$i];
                # Se añade el nodo de pago20:TrasladoP
                $trasladoP = $trasladosP->addChild('xmlns:pago20:TrasladoP');
                $trasladoP->addAttribute('BaseP', $this->formatCurrency(($impuestoPagoData["BaseP"]),$decimalesTotales));
                $trasladoP->addAttribute('ImpuestoP', $impuestoPagoData['ImpuestoP']);
                $trasladoP->addAttribute('TipoFactorP', $impuestoPagoData['TipoFactorP']);
                $trasladoP->addAttribute('TasaOCuotaP', $impuestoPagoData['TasaOCuotaP']);
                $trasladoP->addAttribute('ImporteP', $this->formatCurrency(($impuestoPagoData["ImporteP"]),$decimalesTotales));
            }
        }
        $this->isCFDI = true;
        return $xml;
    }

    public function formatCurrency($monto, $decimales)
    {
        return number_format($monto, $decimales, '.', '');
        //return round($monto, $decimales);
    }

    private function validarXML($xmlString, $isPago = false){
        if($isPago){
            $xsdPath = 'resources/facturacion/plantillas/validadores/pagos20.xsd'; // Ruta local al archivo cfdv40.xsd o URL del esquema XSD
        }else{
            $xsdPath = 'resources/facturacion/plantillas/validadores/cfdv40.xsd'; // Ruta local al archivo cfdv40.xsd o URL del esquema XSD
        }
        
        // Carga el XML
        $doc = new \DomDocument();
        $doc->loadXML($xmlString);
        
        // Habilita la validación
        libxml_use_internal_errors(true);
        
        // Valida el documento XML contra el XSD
        if ($doc->schemaValidate(base_path($xsdPath))) {
            $respuesta = array(
                "valido" => true,
                "mensaje" => "El XML es válido según el esquema XSD."
            );
            return $respuesta;
        } else {
            $error = "El XML no es válido según el esquema XSD. Detalles del error:";
            foreach (libxml_get_errors() as $errorL) {
                $error = $error."\n- ".$errorL->message;
            }
            $respuesta = array(
                "valido" => false,
                "error" => $error
            );
            return $respuesta;
        }
        
    }
}