<?php 

namespace Prophetarum\NfePhp\Services;

use NFePHP\NFe\Tools;
use NFePHP\Common\Certificate;
use NFePHP\NFe\Common\Standardize;

class Communication 
{
    public function consultaContribuinte( $uf, $cnpj, $iest = '', $cpf = '' )
    {
        $response = $this->tools->sefazCadastro( $uf, $cnpj, $iest, $cpf );
    
        //você pode padronizar os dados de retorno atraves da classe abaixo
        //de forma a facilitar a extração dos dados do XML
        //NOTA: mas lembre-se que esse XML muitas vezes será necessário, 
        //      quando houver a necessidade de protocolos
        $arr = $this->extrator( $response );
        
        // file_put_contents( $this->dir . $this->dateTime . '-consulta-contribuinte.txt', json_encode( $arr ) );
        return json_encode( $arr );
        // echo PHP_EOL . ">> CONSULTA CONTRIBUINTE <<" . PHP_EOL;
    }

    public function consultaChave( $chave )
    {
        $response = $this->tools->sefazConsultaChave( $chave );
    
        //você pode padronizar os dados de retorno atraves da classe abaixo
        //de forma a facilitar a extração dos dados do XML
        //NOTA: mas lembre-se que esse XML muitas vezes será necessário, 
        //      quando houver a necessidade de protocolos
        $arr = $this->extrator( $response );

        // file_put_contents( $this->dir . $this->dateTime . '-consulta-chave.txt', json_encode( $arr ) );
        return json_encode( $arr );
        // echo PHP_EOL . ">> CONSULTA CHAVE <<" . PHP_EOL;
    }

    public function confirmaOperacao( $chNFe )
    {
        $tpEvento = '210200'; //confirmacao da operação
        $xJust = ''; //a ciencia não requer justificativa
        $nSeqEvento = 1; //a ciencia em geral será numero inicial de uma sequencia para essa nota e evento

        $response = $this->tools->sefazManifesta( $chNFe, $tpEvento, $xJust = '', $nSeqEvento = 1 );

        //você pode padronizar os dados de retorno atraves da classe abaixo
        //de forma a facilitar a extração dos dados do XML
        //NOTA: mas lembre-se que esse XML muitas vezes será necessário,
        //      quando houver a necessidade de protocolos
        $arr = $this->extrator( $response );
        
        // file_put_contents( $this->dir . $this->dateTime . '-confirma.txt', json_encode( $arr ) );
        return json_encode( $arr );
        // echo PHP_EOL . ">> CONFIRMACAO DA OPERACAO <<" . PHP_EOL;
    }

    public function downloadXml( $chave )
    {
        //só funciona para o modelo 55
        //este serviço somente opera em ambiente de produção
        $response = $this->tools->sefazDownload( $chave );
        // file_put_contents( $this->dir . $this->dateTime . '-download-response.txt', $response );

        // $stz = new Standardize($response);
        // $std = $stz->toStd();

        $std = $this->extrator( $response, "stdClass" );
    
        if ($std->cStat != 138) {
            // echo PHP_EOL . "Documento não retornado. [$std->cStat] $std->xMotivo " . PHP_EOL;
            return;
        }
    
        $zip = $std->loteDistDFeInt->docZip;
        // file_put_contents( $this->dir . $this->dateTime . '-download-zip.txt', $zip );
    
        $arquivo = gzdecode( base64_decode( $zip ) );
        // file_put_contents( $chave . '.xml', $arquivo );
        return $arquivo;
        // echo PHP_EOL . ">> DOWNLOAD OK <<" . PHP_EOL;
    }

    public function cienciaDaOperacao( $chNFe )
    {
        $tpEvento = '210210'; //confirmacao da operação
        $xJust = ''; //a ciencia não requer justificativa
        $nSeqEvento = 1; //a ciencia em geral será numero inicial de uma sequencia para essa nota e evento

        $response = $this->tools->sefazManifesta( $chNFe, $tpEvento, $xJust = '', $nSeqEvento = 1 );
        //você pode padronizar os dados de retorno atraves da classe abaixo
        //de forma a facilitar a extração dos dados do XML
        //NOTA: mas lembre-se que esse XML muitas vezes será necessário,
        //      quando houver a necessidade de protocolos

        $arr = $this->extrator( $response );
        // file_put_contents( $this->dir . $this->dateTime . '-ciencia.txt', json_encode( $arr ) );
        return json_encode( $arr );
        // echo PHP_EOL . ">> CIENCIA DA OPERACAO <<" . PHP_EOL;
    }

    private function extrator( $response, $padraoResposta = "array" )
    {
        $st = new Standardize($response);
        switch ( $padraoResposta ) {
            case "array":
                return $st->toArray(); // nesse caso o $arr irá conter uma representação em array do XML
                break;
            case "stdClass":
                return $st->toStd(); // nesse caso $std irá conter uma representação em stdClass do XML
                break;
            case "json":
                return $st->toJson(); // nesse caso o $json irá conter uma representação em JSON do XML
                break;
        }
    }

    private function getTools( $dados, $caminhoArquivoCertificado, $senhaCertificado, $model = '55', $enviroment = '1')
    {
        $configJson  = json_encode( $dados );
        $certificadoDigital = file_get_contents( $caminhoArquivoCertificado );
        $certificate = Certificate::readPfx( $certificadoDigital, $senhaCertificado );
        $tools = new Tools( $configJson, $certificate );
        $tools->model( $model );
        $tools->setEnvironment( $enviroment );
        return $tools;
    }


    public function __construct( $dados, $caminhoArquivoCertificado, $senhaCertificado )
    {
        $this->tools = $this->getTools( $dados, $caminhoArquivoCertificado, $senhaCertificado );
        $this->dateTime = ( new \DateTime() )->setTimezone( ( new \DateTimeZone('America/Sao_Paulo') )  )->format('Y_m_d_H_i_s');
        $this->dir = 'responses/';
    }

    private $tools;

}