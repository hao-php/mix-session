<?php

namespace Haoa\MixSession;

use Mix\Http\Message\Factory\CookieFactory;
use Mix\Http\Message\Response;
use Mix\Http\Message\ServerRequest;
use Haoa\MixSession\Handler\HandlerInterface;

/**
 * Class Session
 * @package Haoa\MixSession
 * @author liu,jian <coder.keda@gmail.com>
 */
class Session
{

    /**
     * 处理者
     * @var HandlerInterface
     */
    public $handler;

    /**
     * session名
     * @var string
     */
    public $name = 'session_id';

    /**
     * session_id长度
     * @var int
     */
    public $idLength = 26;

    /**
     * 生存时间
     * @var int
     */
    public $maxLifetime = 7200;

    /**
     * 过期时间
     * @var int
     */
    public $cookieExpires = 0;

    /**
     * 有效的服务器路径
     * @var string
     */
    public $cookiePath = '/';

    /**
     * 有效域名/子域名
     * @var string
     */
    public $cookieDomain = '';

    /**
     * 仅通过安全的 HTTPS 连接传给客户端
     * @var bool
     */
    public $cookieSecure = false;

    /**
     * 仅可通过 HTTP 协议访问
     * @var bool
     */
    public $cookieHttpOnly = false;

    /**
     * session_id
     * @var string
     */
    protected $id = '';

    /**
     * @var ServerRequest
     */
    protected $request;

    /**
     * @var Response
     */
    protected $response;

    /**
     * cookie是否已设置
     * @var bool
     */
    protected $cookieSet = false;

    /**
     * 上次设置的cookie过期时间
     * @var int
     */
    protected $lastCookieExpires = 0;

    /**
     * Session constructor.
     * @param HandlerInterface $handler
     */
    public function __construct(HandlerInterface $handler)
    {
        $this->handler = $handler;
    }

    /**
     * 启动新会话或者重用现有会话
     * @param ServerRequest $request
     * @param Response $response
     * @param bool $regenerate
     */
    public function start(ServerRequest $request, Response $response, bool $regenerate = false)
    {
        $this->request  = $request;
        $this->response = $response;
        $sessionId      = $this->request->getAttribute($this->name);
        if ($regenerate || is_null($sessionId)) {
            $sessionId = $this->createId();
        }
        $this->id = $sessionId;
    }

    /**
     * 创建session_id
     * @return string
     */
    protected function createId()
    {
        do {
            $sessionId = static::randomAlphanumeric($this->idLength);
        } while ($this->handler->exists($sessionId));
        return $sessionId;
    }

    /**
     * 获取随机字符
     * @param $length
     * @return string
     */
    protected static function randomAlphanumeric($length)
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz';
        $last  = 61;
        $str   = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= $chars[mt_rand(0, $last)];
        }
        return $str;
    }

    /**
     * 赋值
     * @param string $name
     * @param $value
     * @return bool
     */
    public function set(string $name, $value)
    {
        // 赋值
        $this->handler->set($this->getId(), $name, $value);
        // 更新生存时间
        $this->handler->expire($this->getId(), $this->maxLifetime);
        // 设置cookie（带去重逻辑）
        $this->setCookie();
        return true;
    }

    /**
     * 取值
     * @param string $name
     * @param null $default
     * @return mixed
     */
    public function get(string $name, $default = null)
    {
        // 更新生存时间
        $this->handler->expire($this->getId(), $this->maxLifetime);
        // 返回值
        return $this->handler->get($this->getId(), $name, $default);
    }

    /**
     * 取所有值
     * @return array
     */
    public function all()
    {
        return $this->handler->all($this->getId());
    }

    /**
     * 删除
     * @param string $name
     * @return bool
     */
    public function delete(string $name)
    {
        return $this->handler->delete($this->getId(), $name);
    }

    /**
     * 清除session
     * @return bool
     */
    public function clear()
    {
        return $this->handler->clear($this->getId());
    }

    /**
     * 判断是否存在
     * @param string $name
     * @return bool
     */
    public function has(string $name)
    {
        return $this->handler->has($this->getId(), $name);
    }

    /**
     * 设置cookie（带去重逻辑）
     */
    protected function setCookie()
    {
        $currentExpires = time() + $this->maxLifetime;
        
        // 单个请求内：只设置一次
        // 跨请求：过期时间变化时重新设置
        if ($this->cookieSet && $currentExpires === $this->lastCookieExpires) {
            return;
        }
        
        // 设置cookie
        $factory = new CookieFactory();
        $cookie  = $factory->createCookie($this->name, $this->id, $currentExpires);
        $cookie->withDomain($this->cookieDomain)
            ->withPath($this->cookiePath)
            ->withSecure($this->cookieSecure)
            ->withHttpOnly($this->cookieHttpOnly);
        $this->response->withAddedCookie($cookie);
        
        $this->cookieSet = true;
        $this->lastCookieExpires = $currentExpires;
    }

    /**
     * 获取session_id
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

}
