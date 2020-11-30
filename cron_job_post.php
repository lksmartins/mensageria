<?

require __DIR__ . "/../../vendor/autoload.php";
date_default_timezone_set('America/Sao_Paulo');

// FUNCOES

// funcao para testar porta
function testPort($host='52.44.113.189', $ports=array(61613)){

    if( !is_array($ports) ){
        $ports = array($ports);
    }

    foreach ($ports as $port)
    {
        $connection = @fsockopen($host, $port);

        if (is_resource($connection))
        {
            echo '<h2>' . $host . ':' . $port . ' ' . '(' . getservbyport($port, 'tcp') . ') is open.</h2>' . "\n";

            fclose($connection);
        }

        else
        {
            echo '<h2>' . $host . ':' . $port . ' is not responding.</h2>' . "\n";
        }
    }
}

function toBD($array){

    // if message is blank because of timeout
    if($array==null or $array==''){die;}

    // for test
    echo 'test|';
    print_r($array);
    echo '|';

    foreach( $array as $evento ){
    
        $pdo = new PDO_adm();
        
        //print_r($evento);
        //echo '<hr>';

        $dateGPS = str_replace('T', ' ', $evento['GPS']['@attributes']['dateGPS']);
        $dateSystem = str_replace('T', ' ', $evento['GPS']['@attributes']['dateSystem']);

        if( empty($evento['GPS']['address']) ){$evento['GPS']['address'] = '';}

        $data = [
            'veicId'     => $evento['@attributes']['veicId'],
            'veicTag'    => $evento['@attributes']['veicTag'],
            'dateGPS'    => $evento['GPS']['@attributes']['dateGPS'],
            'dateSystem' => $evento['GPS']['@attributes']['dateSystem'],
            'lng'        => $evento['GPS']['long'],
            'lat'        => $evento['GPS']['lat'],
            'address'    => $evento['GPS']['address'],
            'ignition'   => $evento['panel']['ignition'],
            'speed'      => $evento['panel']['speed'],
            'odometer'   => $evento['panel']['odometer']
        ];
    
        $sql = "
        INSERT INTO 
        eventos (`id`, `veicId`, `veicTag`, `dateDB`, `dateGPS`, `dateSystem`, `lng`, `lat`, `address`, `ignition`, `speed`, `odometer`)
        VALUES (
            NULL, 
            :veicId, 
            :veicTag, 
            current_timestamp(), 
            :dateGPS, 
            :dateSystem, 
            :lng, 
            :lat, 
            :address, 
            :ignition, 
            :speed, 
            :odometer)
            ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);

            //var_dump($stmt);
    
    }

}

function fromDB($placa){

    $pdo = new PDO_adm();

    if($placa==''){
        $placa = 'IYM9822';
    }

    $data = [
        'placa' => $placa
    ];

    $sql = "
        SELECT lat, lng, dateGPS
        FROM eventos
        WHERE veicTag = :placa
        ORDER BY dateGPS DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);
    
    $user = $stmt->fetch();
    //$user[numRows] = $stmt->rowCount();
    
    if( $stmt->rowCount() > 0 ){
        //print_r($user);
        echo 'ok|'.$user['lat'].','.$user['lng'].','.$user['dateGPS'];
    }
    else{
        'erro|Posição não encontrada';
    }

}

function proccessMessage($message, $placa=null){

    if($message==null or $message==''){
        //fromDB($placa);
        die;
    }

    // stomp 2
    $ms = $message;

    $xml = simplexml_load_string($ms);

    $json = json_encode($xml);
    $array = json_decode($json,TRUE);

    toBD($array);

}

function checkStomp(){

    $pdo = new PDO_adm();

    $sql = "
        SELECT *
        FROM stomp
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    $user = $stmt->fetch();
    
    if( $stmt->rowCount() > 0 ){
        //print_r($user);
        if($user['status']<6){
            return true;
        }
        else{
            return false;
        }
    }
    return false;
}

function updateStomp(){

    $pdo = new PDO_adm();

    // somar
    $sql = "
        SELECT *
        FROM stomp
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    $user = $stmt->fetch();
    
    $status = $user['status'] + 1;

    $date = date('H');
    if( $date<21 and $date>6 ){
        $status = 1;
    }

    if($status==6){
        $status = 1; 
    }

    $data = [
        'status' => $status
    ];

    $sql = "
    UPDATE stomp
        SET status = :status
        WHERE
        id = 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);
}

require __DIR__.'/stomp_post.php';

//die;