<?php

namespace Tests\Unit\Actions\Vk;

use App\Actions\Vk\SaveFileVk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SaveFileVkTest extends TestCase
{
    use RefreshDatabase;

    public function test_save_photo(): void
    {
        Http::fake([
            'https://api.vk.com/method/photos.saveMessagesPhoto*' => Http::response([
                'response' => [
                    [
                        'id' => 1,
                        'owner_id' => 1,
                    ],
                ],
            ]),
        ]);

        $responseData = [
            'server' => 906828,
            'photo' => '[{"markers_restarted":true,"photo":"8855b8daae:z","sizes":[],"latitude":0,"longitude":0,"kid":"6b1f9f23332c5f30eaa11acef28609a1","sizes2":[["s","d2ef1ce975749607b9fb4c41cf38e0fdfc6cb7d1ee36a5e3f00236e2","1199825931626004676",75,49],["m","cf8808ebbc7cf2d8f13ad3209f8339f9af3beaeb09ed3a6b4c700b3f","4861523652241854098",130,85],["x","c89b662bad6207ed6c320768c0ba63a0348150f8a0f4941a51f4264d","-8549869287971191896",604,393],["y","f2b531e131f7bb233d5e0f46126c96382eb218cb2811c19377f363ef","-79490864427724083",807,525],["z","c350d6139318501c896eee50f6a4ca9a217cd814c0743e917b630813","-2928471517474729766",1280,833],["o","a8f74958af0d96f8fbcd2379adfd1584c9eeb9afb0a93059ae2d4358","2111863122605808798",130,87],["p","20403b0dd189073f640eec8e5d2956486fc8a5ff1246f8e8bea31358","6631245525053001708",200,133],["q","6fe322664bb1ee71c225e68f720688c5674723b5c7ba4395d91aa105","-2017967277764080434",320,213],["r","ac05857037e24876cdfb35be9a15a79ff4b1d984fc865b4b74b16278","-3358203034298177754",510,340]],"urls":[],"urls2":["0u8c6XV0lge5-0xBzzjg_fxst9HuNqXj8AI24g/xMDe7lOjphA.jpg","z4gI67x88tjxOtMgn4M5-a876usJ7TprTHALPw/kg4JFwGad0M.jpg","yJtmK61iB-1sMgdowLpjoDSBUPig9JQaUfQmTQ/qIN0hi7DWIk.jpg","8rUx4TH3uyM9Xg9GEmyWOC6yGMsoEcGTd_Nj7w/zZLl3nqX5f4.jpg","w1DWE5MYUByJbu5Q9qTKmiF82BTAdD6Re2MIEw/2tARqqv6W9c.jpg","qPdJWK8Nlvj7zSN5rf0VhMnuua-wqTBZri1DWA/npwqJVLYTh0.jpg","IEA7DdGJBz9kDuyOXSlWSG_Ipf8SRvjovqMTWA/7M8u06vqBlw.jpg","b-MiZkux7nHCJeaPcgaIxWdHI7XHukOV2RqhBQ/zoyC2HO9_uM.jpg","rAWFcDfiSHbN-zW-mhWnn_Sx2YT8hltLdLFieA/JqcEASJEZdE.jpg"],"peer_id":222232176}]',
            'hash' => 'b600e0c0e22cbbc5f231e777e4766c93',
        ];

        $resultSave = SaveFileVk::execute('photos', $responseData);

        $this->assertNotEmpty($resultSave->response);
        $this->assertEmpty($resultSave->error_type);
        $this->assertEquals($resultSave->response_code, 200);
    }

    public function test_save_doc(): void
    {
        Http::fake([
            'https://api.vk.com/method/docs.save*' => Http::response([
                'response' => [
                    [
                        'id' => 1,
                        'owner_id' => 1,
                    ],
                ],
            ]),
        ]);

        $responseData = [
            'file' => '222232176|0|-1|909428|58cec28ed8|jpg|83643|file_84.jpg|9d35c54103a98176ef084060650b806e|385a19671c2dc4e6ba58444509e2694d||||eyJkaXNrIjoiNDYiLCJvcmlnX3NpemUiOiIxMjgweDgzMyIsImtpZCI6IjZiMWY5ZjIzMzMyYzVmMzBlOGExM2FjZWYyODYwYmExXG4iLCJzdDJfcHJldmlldyI6IjIhd3NQRzU5SHkzT3lub1BCNzJBRUU4dG1BbjAtNEZMSnJOeHYtVGIzWldyRF9sRm80SkFBVUFBRUVEQUFEQUFBQUEiLCJzdDJfc2hhIjoiMjA2MzdhNzRjZTVhMzAzODYzM2VlMTUwYjA1OWMxZTI5NDUwZTUyM2Y3OTE1OWY5OTFkNzM5MmIiLCJzdDJfc2VjcmV0IjotNTY1MTAyOTE2ODE4OTU2NjAxLCJwZWVyX3NlbmRlciI6Ii0yMTc5MDM0NzQifQ==',
        ];

        $resultSave = SaveFileVk::execute('docs', $responseData);

        $this->assertNotEmpty($resultSave->response);
        $this->assertEmpty($resultSave->error_type);
        $this->assertEquals($resultSave->response_code, 200);
    }
}
