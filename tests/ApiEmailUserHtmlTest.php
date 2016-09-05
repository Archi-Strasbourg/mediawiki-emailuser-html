<?php

namespace ApiEmailUserHtml\Test;

use ApiEmailUserHtml\ApiEmailUserHtml;
use Mockery;
use phpmock\mockery\PHPMockery;

class ApiEmailUserHtmlTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        Mockery::mock('overload:ApiBase');
        Mockery::mock('overload:Status')
            ->shouldReceive('isGood')
                ->andReturn(true);
        Mockery::mock('overload:UserMailer')
            ->shouldReceive('send')
                ->andReturn(new \Status());
        Mockery::mock('overload:Config')
            ->shouldReceive('get')
                ->andReturn('foo');
        Mockery::mock('overload:Hooks')
            ->shouldReceive('run')
                ->andReturn(true);
        Mockery::mock('overload:Message')
            ->shouldReceive('text')
            ->shouldReceive('inContentLanguage')
                ->andReturn(new \Message());
        PHPMockery::mock('ApiEmailUserHtml', 'wfMessage')
            ->andReturn(new \Message());
        Mockery::mock('overload:IContextSource')
            ->shouldReceive('getConfig')
                ->andReturn(new \Config())
            ->shouldReceive('getUser')
            ->shouldReceive('msg')
                ->andReturn(new \Message());
        Mockery::mock('overload:ApiResult')
            ->shouldReceive('addValue');
        Mockery::mock('overload:ApiEmailUser')
            ->shouldReceive('extractRequestParams')
                ->andReturn(
                    array(
                        'target'=>'',
                        'token'=>'',
                        'text'=>'',
                        'html'=>'',
                        'subject'=>'',
                        'ccme'=>''
                    )
                )
            ->shouldReceive('getUser')
            ->shouldReceive('getConfig')
            ->shouldReceive('getContext')
                ->andReturn(new \IContextSource())
            ->shouldReceive('getResult')
                ->andReturn(new \ApiResult())
            ->shouldReceive('getModuleName')
            ->shouldReceive('getAllowedParams')
            ->shouldReceive('getHelpUrls')
                ->andReturn('https://www.mediawiki.org/wiki/API:Email');
        Mockery::mock('overload:User')
            ->shouldReceive('getName');
        Mockery::mock('overload:SpecialEmailUser')
            ->shouldReceive('getTarget')
                ->andReturn(new \User())
            ->shouldReceive('getPermissionsError');
        $mailAddressMock = Mockery::mock('overload:MailAddress');
        $mockAddress = new \MailAddress();
        $mockAddress->name = 'Foo';
        $mailAddressMock
            ->shouldReceive('newFromUser')
                ->andReturn($mockAddress);

        $this->api = new ApiEmailUserHtml();
    }

    protected function tearDown()
    {
        Mockery::close();
    }

    public function testExecute()
    {
        $this->api->execute();
    }

    public function testGetHelpUrls()
    {
        $this->assertEquals(
            array(
                'https://www.mediawiki.org/wiki/API:Email',
                'https://github.com/Archi-Strasbourg/mediawiki-emailuser-html'
            ),
            $this->api->getHelpUrls()
        );
    }

    public function testGetAllowedParams()
    {
        //$this->api->getAllowedParams();
        $this->markTestIncomplete("We can't mock ApiBase::PARAM_TYPE and ApiBase::PARAM_REQUIRED");
    }
}
