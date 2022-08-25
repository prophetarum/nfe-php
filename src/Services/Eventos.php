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
        $resposta = [];

        //executa a busca de DFe em loop
        while ( $ultNSU <= $maxNSU ) {
            
            $iCount++;
                
            if ( $iCount >= $loopLimit ) {
                break;
            }
            
            $resp = $this->tools->sefazDistDFe( $ultNSU );

            if( !$resp ) {
                continue;
            }
                
            //extrair e salvar os retornos
            $dom = new \DOMDocument();
            
            $dom->loadXML( $resp );

            $node = $dom->getElementsByTagName( 'retDistDFeInt' )->item(0);
            $tpAmb = $node->getElementsByTagName( 'tpAmb' )->item(0)->nodeValue;
            $verAplic = $node->getElementsByTagName( 'verAplic' )->item(0)->nodeValue;
            $cStat = $node->getElementsByTagName( 'cStat' )->item(0)->nodeValue;
            $xMotivo = $node->getElementsByTagName( 'xMotivo' )->item(0)->nodeValue;
            $dhResp = $node->getElementsByTagName( 'dhResp' )->item(0)->nodeValue;
            $ultNSU = $node->getElementsByTagName( 'ultNSU' )->item(0)->nodeValue;
            $maxNSU = $node->getElementsByTagName( 'maxNSU' )->item(0)->nodeValue;
            $lote = $node->getElementsByTagName( 'loteDistDFeInt' )->item(0);
            
            if ( in_array( $cStat, [ '137', '656' ] ) ) {
                
                return json_encode([
                    'mensagem' => $xMotivo,
                    'cStat' => $cStat
                ]);

                break;
            }
   
            //lote vazio
            if ( empty( $lote ) ) {
                continue; 
            }
                
            //essas tags irão conter os documentos zipados
            $docs = $lote->getElementsByTagName( 'docZip' );
    
            foreach ( $docs as $doc ) {
                
                $numnsu = $doc->getAttribute( 'NSU' );
                $schema = $doc->getAttribute( 'schema' );
                
                //descompacta o documento e recupera o XML original
                $content = gzdecode( base64_decode( $doc->nodeValue ) );
                
                //identifica o tipo de documento
                $tipo = substr( $schema, 0, 6 );
                
                //processar o conteudo do NSU, da forma que melhor lhe interessar
                //esse processamento depende do seu aplicativo
                $resposta[ 'eventos' ][ $numnsu ] = $content;
                
                if ( $tipo == 'resNFe' ) {
                    $xml = simplexml_load_string( $content );
                    $chave = $xml->chNFe->__toString();
                    $resposta[ 'nfes' ][] = $chave;
                }

            }
            
            //CUIDADO para não deixar seu loop infinito !!
            if ( $ultNSU == $maxNSU ) {
                break;
            }
            
            sleep( 2 );
        }


        $date = ( new \DateTime() )->setTimezone( ( new \DateTimeZone('America/Sao_Paulo') )  );
        $date->add( new \DateInterval( 'PT1H' ) );
        $resposta['log'] = json_encode( $this->log );
        return json_encode( $resposta );
    }



    public function __construct( $dados, $arquivoCertificado, $senha, $ultNSU = '0' )
    {
        $this->log = [];
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