import JSEncrypt from 'jsencrypt/bin/jsencrypt.min'
// 密钥对生成 http://web.chacuo.net/netrsakeypair
import CryptoJS from 'crypto-js'
const key_base = 'dujxmall20221111'
const iv_base = '20221111dujxmall'
// 加密
export function encode(txt) {

  var key_hash = CryptoJS.MD5(key_base);
  var key = CryptoJS.enc.Utf8.parse(key_hash);
  var iv = CryptoJS.enc.Utf8.parse(iv_base);
  var res = CryptoJS.AES.encrypt(txt, key, { iv: iv, mode: CryptoJS.mode.CBC, padding: CryptoJS.pad.ZeroPadding });
  return res.toString();
}

// 解密
export function decode(txt) {

}
