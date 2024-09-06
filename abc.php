<?php 

require 'vendor/autoload.php';
require_once 'constants.php';
require_once 'repetitivefunctions.php';
require_once 'match.php';
require_once 'user.php';
require_once 'leaderboard.php';

/*
$matches = [
    ['u1'=> 1, 'u2' => 2, 'score1' => 5, 'score2' => 6],
    ['u1'=> 1, 'u2' => 3, 'score1' => 7, 'score2' => 6],
    ['u1'=> 3, 'u2' => 2, 'score1' => 6, 'score2' => 6],
    ['u1'=> 1, 'u2' => 4, 'score1' => 3, 'score2' => 6],
    ['u1'=> 1, 'u2' => 1, 'score1' => 5, 'score2' => 6],
    ['u1'=> 4, 'u2' => 3, 'score1' => -5, 'score2' => 6],
    ['u1'=> 6, 'u2' => 3, 'score1' => 5, 'score2' => 66],
    ['u1'=> 8, 'u2' => 4, 'score1' => 55, 'score2' => 6]
];

$matchResult = new MatchResult();
//print_r($matches);

foreach ($matches as $match) {
    //print_r($match);
    print_r($matches[0] . ". eleman"); 
}


//$matchResult->processResult($matches);


1 1 2 3 5 8 13


$a = 0;
$b = 1;
$theend = 20;

echo '1' . ' ';
for ($i = 0; $i < $theend; $i++) {
    $c = $a + $b;
    echo $c . ' ';
    $a = $b;
    $b = $c;
}

$fibonacci = array(0, 1);

$fibonacci[2] = $fibonacci[0] + $fibonacci[1];

echo '1'.' ';
for ($i=2; $i<=100; ++$i ) {
    $fibonacci[$i] = $fibonacci[$i-1] + $fibonacci[$i-2];
    echo $fibonacci[$i].' ';
   }


for ($i = 0; $i < 100; $i++) {
    //echo $i;
    if ($i % 4 == 0) {
        echo $i . ' ' ;
    }
}
    */

    function Factorial($number){ 
        $factorial = 1; 
        for ($i = 1; $i <= $number; $i++){ 
        $factorial = $factorial * $i; 
        } 
        return $factorial; 
    } 
     
    // Driver Code 
    $number = 5; 
    $fact = Factorial($number); 
    echo $fact. ' '; 
    