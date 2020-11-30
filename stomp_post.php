<?php

// como esse serviço roda em cron job, é importante verificar se o serviço está aberto, por isso o uso do semaphore

$key = 99999999999; // id do processo semaphore
$maxAcquire = 1; // quantos processos podem ficar ativos simultaneamente
$permissions = 0666; // permisao
$autoRelease = 0;
 
$semaphore = sem_get($key, $maxAcquire, $permissions, $autoRelease);

//var_dump($semaphore);

if(!$semaphore) {
    echo "Processo em andamento.\n";
    exit;
}

$date = date('H');

if( $date>20 and $date<6 ){
    die;
}

// Biblioteca usada: Enqueue
// https://github.com/php-enqueue/enqueue-dev/blob/4371fce4a577d8b03e2f313e656db4d7c6289705/docs/transport/stomp.md

use Enqueue\Stomp\StompConnectionFactory;

$host = '52.44.113.189'; // ip da mensageria
$port = 61613; // porta da mensageria

$login = 'login';
$pass = 'senha';
$fila = 'nome da fila';

$factory = new StompConnectionFactory([
    'host'     => $host,
    'port'     => $port,
    'login'    => $login,
    'password' => $pass,
    'target'   => 'stomp+activemq:',
    'read_timeout' => 5
]);

$context = $factory->createContext();

$queue = $context->createQueue($fila);
$consumer = $context->createConsumer($queue);

$consumer->setAckMode('client');
$consumer->setPrefetchCount(1000);

echo '<pre>
ACK: '.$consumer->getAckMode().'
Size: '.$consumer->getPrefetchCount();

// recebe a mensagem

try {

    while(true){

        if (sem_acquire($semaphore, 1) !== false) {

            echo '<hr>SEM acquired<hr>';

            $message = $consumer->receive();

            if($message=='' or $message==null or !$message){
                sem_release($semaphore);
                echo '<hr>SEM released<hr>';
                exit;
            }
            
            $ms = $message->getBody();
            
            echo '<textarea rows="30" cols="200">'.$ms.'</textarea><hr>
            ';

            // processa a mensagem
            proccessMessage($ms);

            // deixa o servidor sabendo que recebeu a mensagem
            $consumer->acknowledge($message);

            sleep(1);
            sem_release($semaphore);
            echo '<hr>SEM released<hr>';
            echo '<script>window.scrollTo(0,document.body.scrollHeight);</script>';
        
        } else {
        
            echo 'Processo já em andamento';
            exit;
            
        }

    }

} catch (\Exception $e) {
    // errors

    echo $e;
    sem_release($semaphore);
    echo '<hr>SEM released<hr>';
    exit;
}
