<?php

namespace ApiEmailUserHtml;

use ApiUsageException;
use MailAddress;
use MediaWiki\Api\ApiEmailUser;
use MediaWiki\Api\ApiMain;
use MediaWiki\Context\IContextSource;
use MediaWiki\Context\RequestContext;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Mail\EmailUserFactory;
use MediaWiki\User\UserFactory;
use SpecialEmailUser;
use UserMailer;
use Wikimedia\ParamValidator\ParamValidator;

class ApiEmailUserHtml extends ApiEmailUser
{

    /**
     * @param ApiMain $mainModule
     * @param string $moduleName
     * @param EmailUserFactory $emailUserFactory
     * @param UserFactory $userFactory
     * @param HookContainer $hookContainer
     */
    public function __construct(
        ApiMain                           $mainModule,
        string                            $moduleName,
        private readonly EmailUserFactory $emailUserFactory,
        UserFactory                       $userFactory,
        private readonly HookContainer    $hookContainer
    ) {
        parent::__construct($mainModule, $moduleName, $emailUserFactory, $userFactory);
    }

    private function submit(array $data, IContextSource $context)
    {
        $config = $context->getConfig();

        $target = SpecialEmailUser::getTarget($data['Target'], $this->getUser());
        if (!$target instanceof \User) {
            return $context->msg($target.'text')->parseAsBlock();
        }

        $to = MailAddress::newFromUser($target);
        $from = MailAddress::newFromUser($context->getUser());
        $subject = $data['Subject'];
        $text = $data['Text'];

        $footer = $context->msg(
            'emailuserfooter',
            $from->name,
            $to->name
        )->inContentLanguage()->text();
        $text = rtrim($text)."\n\n-- \n";
        $text .= $footer;

        $html = $data['HTML'];

        $body = [
            'text' => $text,
            'html' => $html,
        ];

        $error = '';
        if (!$this->hookContainer->run('EmailUser', [&$to, &$from, &$subject, &$text, &$error])) {
            return $error;
        }

        if ($config->get('UserEmailUseReplyTo')) {
            $mailFrom = new MailAddress(
                $config->get('PasswordSender'),
                wfMessage('emailsender')->inContentLanguage()->text()
            );
            $replyTo = $from;
        } else {
            $mailFrom = $from;
            $replyTo = null;
        }

        $status = UserMailer::send($to, $mailFrom, $subject, $body, [
            'replyTo' => $replyTo,
        ]);

        if ($status->isGood()) {
            if ($data['CCMe'] && $to != $from) {
                $cc_subject = $context->msg('emailccsubject')->rawParams(
                    $target->getName(),
                    $subject
                )->text();

                $this->hookContainer->run('EmailUserCC', [&$from, &$from, &$cc_subject, &$text]);

                $ccStatus = UserMailer::send($from, $from, $cc_subject, $text);
                $status->merge($ccStatus);
            }

            $this->hookContainer->run('EmailUserComplete', [$to, $from, $subject, $text]);

        }
        return $status;
    }

    /**
     * @throws ApiUsageException
     */
    public function execute(): void {
        $params = $this->extractRequestParams();
        $emailUser = $this->emailUserFactory->newEmailUser( RequestContext::getMain()->getAuthority() );

        $targetUser = SpecialEmailUser::getTarget($params['target'], $this->getUser());
        if (!($targetUser instanceof \User)) {
            $this->dieWithError([$targetUser]);
        }

        $error = $emailUser->canSend();
        if (!$error->isGood()) {
            $this->dieStatus( $error );
        }

        $data = [
            'Target'  => $targetUser->getName(),
            'Text'    => $params['text'],
            'HTML'    => $params['html'],
            'Subject' => $params['subject'],
            'CCMe'    => $params['ccme'],
        ];
        $retval = self::submit($data, $this->getContext());

        if ($retval instanceof \Status) {
            if ($retval->isGood()) {
                $retval = true;
            } else {
                $retval = $retval->getErrorsArray();
            }
        }

        if ($retval === true) {
            $result = ['result' => 'Success'];
        } else {
            $result = [
                'result'  => 'Failure',
                'message' => $retval,
            ];
        }

        $this->getResult()->addValue(null, $this->getModuleName(), $result);
    }

    public function getAllowedParams(): array {
        $params = parent::getAllowedParams();
        $params['html'] = [
            ParamValidator::PARAM_TYPE     => 'text',
            ParamValidator::PARAM_REQUIRED => true,
        ];

        return $params;
    }

    public function getHelpUrls(): array {
        return [parent::getHelpUrls(), 'https://github.com/Archi-Strasbourg/mediawiki-emailuser-html'];
    }
}
