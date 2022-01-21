<?php 

namespace Prophetarum\NfePhp\Services;

use NFePHP\NFe\Tools;
use NFePHP\Common\Certificate;

class Eventos
{

    public function get()
    {
        //este numero deverá vir do banco de dados nas proximas buscas para reduzir 
        //a quantidade de documentos, e para não baixar várias vezes as mesmas coisas.
        $ultNSU = $this->ultNSU;
        $maxNSU = $ultNSU;
        $loopLimit = 50;
        $iCount = 0;
        $x = 0;
        $resposta = [];
    
        //executa a busca de DFe em loop
        while ($ultNSU <= $maxNSU) {
            $iCount++;
    
            if ($iCount >= $loopLimit) {
                break;
            }

            try {
                //executa a busca pelos documentos
                $resp = $this->tools->sefazDistDFe( $ultNSU );
                // file_put_contents( $this->pastaDownloads . $this->dateTime . '-' . $x . '-resp.txt', $resp);
                $x++;
            } catch (\Exception $e) {
                echo $e->getMessage(); //tratar o erro
            }
        
            //extrair e salvar os retornos
            $dom = new \DOMDocument();
            $dom->loadXML($resp);
    
            $node = $dom->getElementsByTagName('retDistDFeInt')->item(0);
            $tpAmb = $node->getElementsByTagName('tpAmb')->item(0)->nodeValue;
            $verAplic = $node->getElementsByTagName('verAplic')->item(0)->nodeValue;
            $cStat = $node->getElementsByTagName('cStat')->item(0)->nodeValue;
            $xMotivo = $node->getElementsByTagName('xMotivo')->item(0)->nodeValue;
            $dhResp = $node->getElementsByTagName('dhResp')->item(0)->nodeValue;
            $ultNSU = $node->getElementsByTagName('ultNSU')->item(0)->nodeValue;
            $maxNSU = $node->getElementsByTagName('maxNSU')->item(0)->nodeValue;
            $lote = $node->getElementsByTagName('loteDistDFeInt')->item(0);
    
            if ( empty( $lote ) ) {
                echo PHP_EOL . "Lote vazio" . PHP_EOL;
                continue; //lote vazio
            }
    
            //essas tags irão conter os documentos zipados
            $docs = $lote->getElementsByTagName('docZip');
    
            foreach ($docs as $doc) {
                $numnsu = $doc->getAttribute('NSU');
                $schema = $doc->getAttribute('schema');

                //descompacta o documento e recupera o XML original
                $content = gzdecode( base64_decode( $doc->nodeValue ) );

                //identifica o tipo de documento
                $tipo = substr( $schema, 0, 6 );
                //processar o conteudo do NSU, da forma que melhor lhe interessar
                //esse processamento depende do seu aplicativo

                echo PHP_EOL . "NSU: $numnsu :" . PHP_EOL . "$content" . PHP_EOL;
                $resposta['eventos'][$numnsu] = $content;
                
                if ($tipo == 'resNFe') {
                    $xml = simplexml_load_string( $content );
                    $chave = $xml->chNFe->__toString();
                    echo PHP_EOL . $chave . PHP_EOL;
                    $resposta['nfes'][] = $chave;
                }
            }
            
            if ($ultNSU == $maxNSU) {
                break; //CUIDADO para não deixar seu loop infinito !!
            }
    
            sleep(2);
        }

        echo PHP_EOL . "Ultimo NSU: $ultNSU" . PHP_EOL;
        $date = ( new \DateTime() )->setTimezone( ( new \DateTimeZone('America/Sao_Paulo') )  );
        $date->add( new \DateInterval( 'PT1H' ) );
        $resposta['ultimo-nsu'] = $ultNSU;
        $resposta['proxima-consulta'] = $date->format('Y-m-d H:i:s');
        // file_put_contents( $this->pastaDownloads . $this->dateTime . '-' . $x . '-resposta.txt', json_encode($resposta) );
        // mudar arquivo retorno, entre outros mandar o ultimo nsu

        return $resposta;
    }



    public function __construct( $dados, $arquivoCertificado, $senha, $ultNSU = '0' )
    {
        $certificate = Certificate::readPfx( file_get_contents( $arquivoCertificado ), $senha );
        $this->tools = new Tools( json_encode( $dados ), $certificate );
    
        //só funciona para o modelo 55
        $this->tools->model('55');
    
        //este serviço somente opera em ambiente de produção
        $this->tools->setEnvironment(1);

        $this->ultNSU = $ultNSU;

        $this->pastaDownloads = getcwd() . ( '/responses//' );
        $this->dateTime = ( new \DateTime() )->setTimezone( ( new \DateTimeZone('America/Sao_Paulo') )  )->format('Y_m_d_H_i_s');
    }


}