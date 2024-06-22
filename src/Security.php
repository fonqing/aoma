<?php

namespace aoma;

class Security
{
    private const IV = 'a_o_m_a_s_o_f_t_';

    /**
     * Encrypt data
     *
     * @param  mixed  $data 要加密的数据
     * @param  string $key  加密密钥
     * @return string
     */
    public static function encode(mixed $data, string $key = ''): string
    {
        return base64_encode(openssl_encrypt(serialize($data), 'AES-256-CBC', $key ?? self::IV, 0, self::IV));
    }

    /**
     * Decrypt data
     *
     * @param  mixed  $data 要解密的数据
     * @param  string $key  解密密钥
     * @return mixed
     */
    public static function decode(mixed $data, string $key = ''): mixed
    {
        $data = openssl_decrypt(base64_decode($data), 'AES-256-CBC', $key ?? self::IV, 0, self::IV);
        return unserialize($data);
    }
}
