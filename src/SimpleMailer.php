<?php

declare(strict_types=1);

namespace Zii\Util;

use Yii;
use yii\base\InvalidConfigException;
use Throwable;

class SimpleMailer
{
    public const EVENT_beforeMailSend = 'SimpleMailer_beforeMailSend';
    public const EVENT_afterMailSend = 'SimpleMailer_afterMailSend';

    /**
     * @var string|array 目标地址
     */
    public $address;
    /**
     * @var string 邮件主题
     */
    public string $subject;
    /**
     * @var string|null 邮件内容
     */
    public ?string $content = null;
    /**
     * @var array 内容参数[view, params]
     */
    public array $compose = [];
    /**
     * @var array 附件地址列表 filePath => fileName
     */
    public array $attachments = [];
    /**
     * @var string|array 显式抄送地址列表
     */
    public $cc = [];
    /**
     * @var string|array 隐式抄送地址列表
     */
    public $bcc = [];

    /**
     * @throws InvalidConfigException
     */
    public function prepare(): void
    {
        if (empty($this->address)) {
            throw new InvalidConfigException('邮件地址不能为空');
        }
        if ($this->subject === null) {
            throw new InvalidConfigException('邮件主题不能为空');
        }
        if ($this->content === null && empty($this->compose)) {
            throw new InvalidConfigException('邮件内容不能为空');
        }

        if (is_string($this->address)) {
            $this->address = [$this->address];
        }
        if (is_string($this->cc)) {
            $this->cc = [$this->cc];
        }
        if (is_string($this->bcc)) {
            $this->bcc = [$this->bcc];
        }

        foreach ($this->address as $address) {
            if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
                throw new InvalidConfigException("邮箱地址{$address}不合法");
            }
        }
        foreach ($this->cc as $cc) {
            if (filter_var($cc, FILTER_VALIDATE_EMAIL) === false) {
                throw new InvalidConfigException("显式抄送地址{$cc}不合法");
            }
        }
        foreach ($this->bcc as $bcc) {
            if (filter_var($bcc, FILTER_VALIDATE_EMAIL) === false) {
                throw new InvalidConfigException("隐式抄送地址{$bcc}不合法");
            }
        }
        foreach ($this->attachments as $filePath => $fileName) {
            if (!file_exists($filePath) || !is_file($filePath)) {
                throw new InvalidConfigException("附件{$filePath}不存在");
            }
        }
    }

    /**
     * @throws InvalidConfigException
     */
    public function send(): bool
    {
        $this->prepare();

        $mailer = Yii::$app->mailer->compose($this->compose['view'] ?? null, $this->compose['params'] ?? [])
            ->setTo($this->address)
            ->setCc($this->cc)
            ->setBcc($this->bcc)
            ->setSubject($this->subject)
            ->setCharset('UTF-8');

        if (empty($this->compose)) {
            $mailer->setHtmlBody($this->content);
        }

        foreach ($this->attachments as $filePath => $fileName) {
            $mailer->attach($filePath, [
                'fileName' => $fileName,
            ]);
        }

        try {
            return $mailer->send();
        } catch (Throwable $e) {
            Yii::error([$e->getMessage(), $e->getCode(), $e->getFile(), $e->getLine(), $e->getTrace()]);
            return false;
        }
    }
}
