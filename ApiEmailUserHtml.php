<?php

namespace ApiEmailUserHtml;

class ApiEmailUserHtml extends \ApiEmailUser
{


    private function submit(array $data, \IContextSource $context)
    {
        $config = $context->getConfig();

        $target = \SpecialEmailUser::getTarget($data['Target']);
        if (!$target instanceof \User) {
            return $context->msg($target . 'text')->parseAsBlock();
        }

        $to = \MailAddress::newFromUser($target);
        $from = \MailAddress::newFromUser($context->getUser());
        $subject = $data['Subject'];
        $text = $data['Text'];

        $footer = $context->msg(
            'emailuserfooter',
            $from->name,
            $to->name
        )->inContentLanguage()->text();
        $text = rtrim($text) . "\n\n-- \n";
        $text .= $footer;

        $html = $data['HTML'].'<br/><br/><hr/>';
        $html .= $footer;

        $body = array(
            'text'=>$text,
            'html'=>$html
        );

        $error = '';
        if (!\Hooks::run('EmailUser', array( &$to, &$from, &$subject, &$text, &$error ))) {
            return $error;
        }

        if ($config->get('UserEmailUseReplyTo')) {
            $mailFrom = new \MailAddress(
                $config->get('PasswordSender'),
                wfMessage('emailsender')->inContentLanguage()->text()
            );
            $replyTo = $from;
        } else {
            $mailFrom = $from;
            $replyTo = null;
        }

        $status = \UserMailer::send($to, $mailFrom, $subject, $body, array(
            'replyTo' => $replyTo,
        ));

        if (!$status->isGood()) {
            return $status;
        } else {
            if ($data['CCMe'] && $to != $from) {
                $cc_subject = $context->msg('emailccsubject')->rawParams(
                    $target->getName(),
                    $subject
                )->text();

                \Hooks::run('EmailUserCC', array( &$from, &$from, &$cc_subject, &$text ));

                $ccStatus = UserMailer::send($from, $from, $cc_subject, $text);
                $status->merge($ccStatus);
            }

            \Hooks::run('EmailUserComplete', array( $to, $from, $subject, $text ));

            return $status;
        }
    }

    public function execute()
    {
        $params = $this->extractRequestParams();

        $targetUser = \SpecialEmailUser::getTarget($params['target']);
        if (!( $targetUser instanceof \User )) {
            $this->dieUsageMsg(array( $targetUser ));
        }

        $error = \SpecialEmailUser::getPermissionsError(
            $this->getUser(),
            $params['token'],
            $this->getConfig()
        );
        if ($error) {
            $this->dieUsageMsg(array( $error ));
        }

        $data = array(
            'Target' => $targetUser->getName(),
            'Text' => $params['text'],
            'HTML' => $params['html'],
            'Subject' => $params['subject'],
            'CCMe' => $params['ccme'],
        );
        $retval = self::submit($data, $this->getContext());

        if ($retval instanceof \Status) {
            if ($retval->isGood()) {
                $retval = true;
            } else {
                $retval = $retval->getErrorsArray();
            }
        }

        if ($retval === true) {
            $result = array( 'result' => 'Success' );
        } else {
            $result = array(
                'result' => 'Failure',
                'message' => $retval
            );
        }

        $this->getResult()->addValue(null, $this->getModuleName(), $result);
    }


    public function getAllowedParams()
    {
        $params = parent::getAllowedParams();
        $params['html'] = array(
            \ApiBase::PARAM_TYPE => 'text',
            \ApiBase::PARAM_REQUIRED => true
        );
        return $params;
    }

    public function getHelpUrls()
    {
        return array(parent::getHelpUrls(), 'https://github.com/Archi-Strasbourg/mediawiki-emailuser-html');
    }
}
