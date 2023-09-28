<?php declare(strict_types=1);

namespace XRPLWin\XRPLNFTTxMutatationParser\Tests;

use PHPUnit\Framework\TestCase;
use XRPLWin\XRPLNFTTxMutatationParser\NFTTxMutationParser;

/***
 * NFTokenPage is modified, token is minted on behalf of issuer.
 * @see https://hash.xrp.fans/D904ADB2D6DD9644B7ACC14E351536B8570F8451AAB01E946ADB47B1E381399F/json
 */
final class Tx01Test extends TestCase
{
    public function testFoo()
    {
        $transaction = file_get_contents(__DIR__.'/fixtures/tx01.json');
        $transaction = \json_decode($transaction);
        
    }
}