<?php


namespace FernandoMelo\CFe;

use Dompdf\Dompdf;
use FernandoMelo\Common\Twig;
use FernandoMelo\Utils\Mask;

class Extrato
{
    /**
     * @var \SimpleXMLElement|string
     */
    protected $xml;

    /**
     * @var string
     */
    protected $logo;

    /**
     * @var string
     */
    protected $infoConsultaAplicativo;

    /**
     * @var string
     */
    protected $content;


    /**
     * Extrato constructor.
     * @param $xml
     * @param $logo
     */
    public function __construct(string $xml, string $logo = '', $infoConsultaAplicativo = "")
    {
        $this->xml = (!is_file($xml))
            ? simplexml_load_string($xml)
            : simplexml_load_file($xml);


        if ($logo) {
            $type = pathinfo($logo, PATHINFO_EXTENSION);
            $data = file_get_contents($logo);
            $this->logo = 'data:image/' . $type . ';base64,' . base64_encode($data);
        }

        $this->infoConsultaAplicativo = $infoConsultaAplicativo;

        $this->render();
    }


    /**
     * @return string
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    private function make()
    {
        $attr = '@attributes';
        $std = new \stdClass();
        $std->logo = $this->logo;
        $std->emitente = $this->xml->infCFe->emit;

        $std->emitente->CNPJ = Mask::mask((string)$std->emitente->CNPJ, "##.###.###/####-##");
        $std->emitente->enderEmit->CEP = vsprintf("%s%s%s%s%s-%s%s%s", str_split($std->emitente->enderEmit->CEP));

        $std->ide = $this->xml->infCFe->ide;
        $std->ide->nserieSAT = Mask::mask((string)$std->ide->nserieSAT, "###.###.###");

        $std->total = $this->xml->infCFe->total;

        $dataStr = (string)$this->xml->infCFe->ide->dEmi;
        $horaStr = (string)$this->xml->infCFe->ide->hEmi;

        $std->emitidaEm = \DateTime::createFromFormat('YmdHis', "{$dataStr}{$horaStr}");

        $std->chave = substr((string)$this->xml->infCFe->attributes()["Id"], 3);

        $adquirente = "";
        if ($this->xml->infCFe->dest) {
            $std->destinatario = $this->xml->infCFe->dest;
            $adquirente = isset($std->destinatario->CNPJ) ? (string)$std->destinatario->CNPJ : (string)$std->destinatario->CPF;

            if (strlen($adquirente) == 11) {
                $std->destinatario->identificaoCliente = Mask::mask($adquirente, "###.###.###-##");
            } else if (strlen($adquirente) == 14) {
                $std->destinatario->identificaoCliente = Mask::mask($adquirente, "##.###.###/####-##");
            }
        }

        $std->qrCode = "{$std->chave}|{$std->emitidaEm->format('YmdHis')}|{$std->total->vCFe}|{$adquirente}|{$std->ide->assinaturaQRCODE}";

        $produtos = [];
        if (count($this->xml->infCFe->det) > 1) {
            foreach ($this->xml->infCFe->det as $prod) {
                unset($prod->$attr);
                $produtos[] = $prod;
            }
        } else {
            unset($this->xml->infCFe->det->$attr);
            $produtos[] = $this->xml->infCFe->det;
        }
        $std->produtos = $produtos;

        $pagamentos = [];
        if (count($this->xml->infCFe->pgto->MP) > 1) {
            foreach ($this->xml->infCFe->pgto->MP as $pgto) {
                $pagamentos[] = $pgto;
            }
        } else {
            $pagamentos[] = $this->xml->infCFe->pgto->MP;
        }
        $std->pagamentos = $pagamentos;

        $std->vTroco = $this->xml->infCFe->pgto->vTroco;

        $std->informacao = $this->xml->infCFe->infAdic;

        if (isset($this->xml->infCFe->entrega)) {
            $std->entrega = $this->xml->infCFe->entrega;
        }

        $std->infoConsultaAplicativo = $this->infoConsultaAplicativo;

        $twig = new Twig();

        return $twig->render('extrato.html.twig', (array)$std);
    }

    /**
     * @return string
     */
    private function render()
    {
        $this->content = $this->make();
    }

    /**
     * @return string
     */
    public function html()
    {
        return $this->content;
    }

    /**
     * @param false $download
     * @return string|null
     */
    public function pdf($download = false)
    {
        // instantiate and use the dompdf class
        $dompdf = new Dompdf();
        $dompdf->loadHtml($this->content);

        // (Optional) Setup the paper size and orientation
        $dompdf->setPaper([0, 0, 235.00, 841.89], 'portrait');

        // Render the HTML as PDF
        $dompdf->render();

        if ($download) {
            // Output the generated PDF to Browser
            $chave = (string)$this->xml->infCFe->attributes()["Id"];
            $dompdf->stream($chave);
        }
        return $dompdf->output();
    }

}