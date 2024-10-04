<?php

namespace App\Services;

use GuzzleHttp\Client;

class FinkokService
{
    protected $client;
    protected $username;
    protected $password;
    protected $url;

    public function __construct()
    {
        $env = env('TIMBRADO_PRODUCCION', false);
        $this->client = new Client();
        $this->username = env('FINKOK_USERNAME');
        $this->password = env('FINKOK_PASSWORD');
        $this->urlTimbrado = $env ? env('FINKOK_URL_TIMBRADO') : 'http://demo-facturacion.finkok.com/servicios/soap/stamp.wsdl'; 
        $this->urlRetenciones = $env ? env('FINKOK_URL_RETENCIONES') : 'http://demo-facturacion.finkok.com/servicios/soap/retentions.wsdl'; 
        $this->urlCancelacion = $env ? env('FINKOK_URL_CANCELACION') : 'http://demo-facturacion.finkok.com/servicios/soap/cancel.wsdl'; 

    }

    public function stamp($xml)
    {
        $response = $this->client->post($this->url . '/stamp', [
            'auth' => [$this->username, $this->password],
            'body' => $xml
        ]);

        return $response->getBody()->getContents();
    }

    // Agrega más métodos según sea necesario
}
