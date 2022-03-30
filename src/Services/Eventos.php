<?php 

namespace Prophetarum\NfePhp\Services;

use NFePHP\NFe\Tools;
use NFePHP\Common\Certificate;

class Eventos
{

    public function dateTime()
    {
        $dateTime =  ( new \DateTime() )->setTimezone( ( new \DateTimeZone('America/Sao_Paulo') )  );
        return $dateTime->format('Y-m-d H:i:s');
    }

    public function linha( $linha )
    {
        $this->log[] = PHP_EOL . $this->dateTime() . ": " . $linha . PHP_EOL;
        // echo PHP_EOL . $this->dateTime() . ": " . $linha . PHP_EOL;
    }

    public function get()
    {
        $this->linha( 'Entrou na funcao get ' );
        
        //este numero deverá vir do banco de dados nas proximas buscas para reduzir 
        //a quantidade de documentos, e para não baixar várias vezes as mesmas coisas.
        $ultNSU = $this->ultNSU;
        $maxNSU = $ultNSU;
        $loopLimit = 50;
        $iCount = 0;
        $x = 0;
        $resposta = [];

        $this->linha(
            ' ultNSU: ' . $ultNSU . 
            ' maxNSU: ' . $maxNSU . 
            ' loopLimit: ' . $loopLimit . 
            ' iCount: ' . $iCount . 
            ' x: ' . $x . 
            ' resposta: ' . count($resposta)
        );
    
        //executa a busca de DFe em loop
        while ( $ultNSU <= $maxNSU ) {
            $this->linha( '>>> Entrou no while: $ultNSU <= $maxNSU <<<' );
            $this->linha(
                ' ultNSU: ' . $ultNSU . 
                ' maxNSU: ' . $maxNSU
            );

            $iCount++;
            $this->linha( 'iCount:' . $iCount );
    
            if ( $iCount >= $loopLimit ) {
                $this->linha( 'Entrou no if $iCount >= $loopLimit' );
                $this->linha( 
                    ' iCount: ' . $iCount . 
                    ' loopLimit: ' . $loopLimit
                );

                $this->linha( '!! >>> Break <<< !!' );
                break;
            }

            $this->linha( 'Não entrou no if: $iCount >= $loopLimit' );

            try {
                $this->linha( 'entrou no try' );
                //executa a busca pelos documentos
                $this->linha( 'executa a busca pelos documentos' );

                // if( $ultNSU == 0 ) {
                //     throw new \Exception( "ultNSU está com valor zerado" );
                // }

                // var_dump( $ultNSU , $maxNSU );
                // die();
                $resp = $this->tools->sefazDistDFe( $ultNSU );
                $this->linha( 'salva em arquivo a busca pelos documentos' );
                // file_put_contents( $this->pastaDownloads . $this->dateTime . '-' . $x . '-resp.txt', $resp);

                // $resp = file_get_contents( $this->pastaDownloads . "2022_03_29_16_48_35-busca-por-documentos.txt");
                // $resp = null;

                if( !$resp ) {
                    $this->linha( 'Entrou no if !$resp:' );
                    $this->linha( '>>>> Continue <<<<' );
                    continue;
                }
                $this->linha( 'Não entrou no if !$resp:' );

                // file_put_contents( $this->pastaDownloads . $this->dateTime . '-' . $x . '-resp.txt', $resp);
                $x++;
                $this->linha( 'x: ' . $x);
                

            } catch (\Exception $e) {
                echo "Erro: " . $e->getMessage();
            }
        
            //extrair e salvar os retornos
            $dom = new \DOMDocument();
            $this->linha( 'Criou Dom:' );

            try{
                $dom->loadXML( $resp );
            } catch ( \Exception $e ) {
                echo "Erro ao criar dom: " . $e->getMessage();
            }

            $this->linha( 'Leu $resp:' );

            $node = $dom->getElementsByTagName('retDistDFeInt')->item(0);
            $this->linha( 'node: ' . $dom->saveHTML( $node ) );

            $tpAmb = $node->getElementsByTagName('tpAmb')->item(0)->nodeValue;
            $this->linha( 'tbAmb: ' . $tpAmb );

            $verAplic = $node->getElementsByTagName('verAplic')->item(0)->nodeValue;
            $this->linha( 'verAplic: ' . $verAplic );

            $cStat = $node->getElementsByTagName('cStat')->item(0)->nodeValue;
            $this->linha( 'cStat: ' . $cStat );

            $xMotivo = $node->getElementsByTagName('xMotivo')->item(0)->nodeValue;
            $this->linha( 'xMotivo: ' . $xMotivo );

            $dhResp = $node->getElementsByTagName('dhResp')->item(0)->nodeValue;
            $this->linha( 'dhResp: ' . $dhResp );

            $ultNSU = $node->getElementsByTagName('ultNSU')->item(0)->nodeValue;
            $this->linha( 'ultNSU: ' . $ultNSU );

            $maxNSU = $node->getElementsByTagName('maxNSU')->item(0)->nodeValue;
            $this->linha( 'maxNSU: ' . $maxNSU );

            $lote = $node->getElementsByTagName('loteDistDFeInt')->item(0);
            $this->linha( 'lote: ' .  $dom->saveHTML( $lote ) );

            if ( in_array( $cStat, ['137', '656'] ) ) {
                $this->linha( 'Erro: ' .  $cStat . ": " . $xMotivo );

                return json_encode([
                    'mensagem' => $xMotivo,
                    'cStat' => $cStat
                ]);
                break;
            }
   
            if ( empty( $lote ) ) {
                $this->linha( 'Entrou no empty( $lote ) ' );
                $this->linha( '>>>> Continue <<<<' );
                continue; //lote vazio
            }

            $this->linha( 'Não entrou no empty( $lote ) ' );
    
            //essas tags irão conter os documentos zipados
            $docs = $lote->getElementsByTagName('docZip');
            // $this->linha( '$docs: ' .  $dom->saveHTML( $docs ) );

    
            foreach ( $docs as $doc ) {
                $this->linha( '>>>> Entrou no foreach <<<<' );

                $numnsu = $doc->getAttribute('NSU');
                $this->linha( 'numnsu: ' . json_encode( $numnsu ) );
                $schema = $doc->getAttribute('schema');
                $this->linha( 'schema: ' . json_encode( $schema ) );

                //descompacta o documento e recupera o XML original
                $content = gzdecode( base64_decode( $doc->nodeValue ) );
                $this->linha( 'content: ' . json_encode( $content ) );

                //identifica o tipo de documento
                $tipo = substr( $schema, 0, 6 );
                $this->linha( 'tipo: ' . json_encode( $tipo ) );

                //processar o conteudo do NSU, da forma que melhor lhe interessar
                //esse processamento depende do seu aplicativo

                // echo PHP_EOL . "NSU: $numnsu :" . PHP_EOL . "$content" . PHP_EOL;
                $resposta[ 'eventos' ][ $numnsu ] = $content;
                $this->linha( 'resposta: ' . json_encode( $resposta ) );

                if ( $tipo == 'resNFe' ) {
                    $this->linha( 'entrou no if $tipo == resNFe' );
                    $xml = simplexml_load_string( $content );
                    $chave = $xml->chNFe->__toString();
                    // echo PHP_EOL . $chave . PHP_EOL;
                    $resposta['nfes'][] = $chave;
                }

                $this->linha( '>>>> Fim do foreach <<<<' );
            }
            
            if ( $ultNSU == $maxNSU ) {
                $this->linha( 'if $ultNSU == $maxNSU' );
                $this->linha( '!! >>> Break <<< !!' );
                break; //CUIDADO para não deixar seu loop infinito !!
            }
            
            $this->linha( 'sleep' );
            sleep(2);
            $this->linha( '>>> Fim do while <<<' );

        }

        // echo PHP_EOL . "Ultimo NSU: $ultNSU" . PHP_EOL;
        $date = ( new \DateTime() )->setTimezone( ( new \DateTimeZone('America/Sao_Paulo') )  );
        $date->add( new \DateInterval( 'PT1H' ) );
        // $resposta['ultimo-nsu'] = $ultNSU;
        // $resposta['proxima-consulta'] = $date->format('Y-m-d H:i:s');
        // file_put_contents( $this->pastaDownloads . $this->dateTime . '-' . $x . '-resposta.txt', json_encode($resposta) );
        // mudar arquivo retorno, entre outros mandar o ultimo nsu

        $this->linha( 'retorna $resposta: ' . json_encode( $resposta ) );
        // file_put_contents( 'log.txt', json_encode( $this->log ) );
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