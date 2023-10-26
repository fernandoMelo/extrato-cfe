# Extrato CF-e

## Requirements
* PHP version 8.2 or higher


## Install with composer

```bash
composer require fernandoMelo/extrato-cfe
```


## Quick Start

```php
use FernandoMelo\CFe\Extrato;

$xml = __DIR__ . 'path_to_xml';

$logo = __DIR__ . 'path_to_logo';


$infoConsultaAplicativo = "Informação para consulta do QrCode via aplicativo utilizado no estado";

$extrato = new Extrato($xml, $logo, $infoConsultaAplicativo);

//Obtêm extrato em HTML
$html = $extrato->html();

//Obtêm extrato em PDF 
$pdf = $extrato->pdf();

//Realiza download do extrato em PDF
$extrato->pdf(true);


```