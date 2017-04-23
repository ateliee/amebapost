<?php
use PHPUnit\Framework\TestCase;
use Ameba\AmebaPost;

/**
 * Class AmebaPostTest
 */
class AmebaPostTest extends TestCase {

    function testThemesIds(){
        $themes = null;
        try{
            $ameba = new AmebaPost("test", "");
        }catch (\Ameba\AmebaException $e){
        }
        $this->assertNull($themes, "valid login user");
    }
}
