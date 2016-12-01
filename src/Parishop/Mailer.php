<?php
namespace Parishop;

class Mailer
{
    /**
     * @var \PHPixie\Template
     */
    protected $template;

    /**
     * @var \PHPixie\Slice\Type\ArrayData
     */
    protected $config;

    /**
     * @var \Swift_Mailer[]
     */
    protected $instances = [];

    /**
     * @param \PHPixie\Template             $template
     * @param \PHPixie\Slice\Type\ArrayData $config
     */
    public function __construct($template, $config)
    {
        $this->config   = $config;
        $this->template = $template;
        $preferences    = \Swift_Preferences::getInstance();
        if(!@is_dir(__DIR__ . '/tmp')) {
            @mkdir(__DIR__ . '/tmp', 0755, true);
        }
        $preferences->setTempDir(__DIR__ . '/tmp')->setCacheType('disk');
    }

    /**
     * @param mixed  $to
     * @param mixed  $from
     * @param string $subject
     * @param string $message
     * @param bool   $html
     * @param string $config
     * @return int
     * @throws \Exception
     */
    public function send($to, $from, $subject, $message, $html = false, $config = 'default')
    {
        $message = \Swift_Message::newInstance($subject, $message, $html ? 'text/html' : 'text/plain', 'utf-8');
        if(is_string($to)) {
            $to = array('to' => array($to));
        } elseif(is_array($to) && is_string(key($to)) && is_string(current($to))) {
            $to = array('to' => array($to));
        } elseif(is_array($to) && is_numeric(key($to))) {
            $to = array('to' => $to);
        }
        foreach($to as $type => $set) {
            $type = strtolower($type);
            if(!in_array($type, array('to', 'cc', 'bcc'), true)) {
                throw new \Exception("You can only specify 'To', 'Cc' or 'Bcc' recepients. You attempted to specify {$type}.");
            }
            $method = 'add' . ucfirst($type);
            foreach($set as $recepient) {
                if(is_array($recepient)) {
                    $message->$method(key($recepient), current($recepient));
                } else {
                    $message->$method($recepient);
                }
            }
        }
        if($from === null) {
            $from = $this->config->get("{$config}.sender");
        }
        if(is_array($from)) {
            $message->setFrom(key($from), current($from));
        } else {
            $message->setFrom($from);
        }

        return $this->mailer($config)->send($message);
    }

    /**
     * @param string $email
     * @param string $subject
     * @param string $templateName
     * @param array  $data
     * @param string $config
     * @return int
     * @throws \Exception
     */
    public function sendTemplate($email, $subject, $templateName, array $data = [], $config = 'default')
    {
        $container = $this->template->get($templateName, $data);

        return $this->send($email, $this->config->get($config . '.from'), $subject, $container->render(), true, $config);
    }

    /**
     * @param $config
     * @return \Swift_Mailer
     * @throws \Exception
     */
    protected function build_mailer($config)
    {
        $type = $this->config->get("{$config}.type", 'native');
        switch($type) {
            case 'smtp':
                $transport = \Swift_SmtpTransport::newInstance(
                    $this->config->get("{$config}.hostname", 'localhost'),
                    $this->config->get("{$config}.port", 25)
                );
                if(($encryption = $this->config->get("{$config}.encryption", false)) !== false) {
                    $transport->setEncryption($encryption);
                }
                if(($username = $this->config->get("{$config}.username", false)) !== false) {
                    $transport->setUsername($username);
                }
                if(($password = $this->config->get("{$config}.password", false)) !== false) {
                    $transport->setPassword($password);
                }
                $transport->setTimeout($this->config->get("{$config}.timeout", 5));
                break;
            case 'sendmail':
                $transport = \Swift_SendmailTransport::newInstance($this->config->get("{$config}.sendmail_command", "/usr/sbin/sendmail -bs"));
                break;
            case 'native':
                $transport = \Swift_MailTransport::newInstance($this->config->get("{$config}.mail_parameters", "-f%s"));
                break;
            default:
                throw new \Exception("Connection can be one of the following: smtp, sendmail or native. You specified '{$type}' as type");
        }

        return \Swift_Mailer::newInstance($transport);
    }

    /**
     * @param string $config
     * @return \Swift_Mailer
     * @throws \Exception
     */
    protected function mailer($config)
    {
        if(!isset($this->instances[$config])) {
            $this->instances[$config] = $this->build_mailer($config);
        }

        return $this->instances[$config];
    }

}