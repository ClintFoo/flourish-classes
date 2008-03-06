<?php
/**
 * Provides cryptography functionality, including hashing and encrypting
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @link  http://flourishlib.com/fCryptography
 * 
 * @uses  fCore
 * @uses  fProgrammerException
 * 
 * @version  1.0.0
 * @changes  1.0.0    The initial implementation [wb, 2007-11-27]
 */
class fCryptography
{

	/**
	 * Prevent instantiation
	 * 
	 * @return fSecurity
	 */
	private function __construct() { }
	
	
	/**
	 * Returns a random string of the length specified
	 * 
	 * @param  integer $length  The length of string to return
	 * @param  string $type		The type of string to return, can be 'alphanumeric', 'alpha', 'numeric', or 'hexadecimal'
	 * @return string  A random string of the length specified
	 */
	static public function generateRandomString($length, $type='alphanumeric')
	{
		if ($length < 1) {
			fCore::toss('fProgrammerException', 'Length of string specified, ' . $length . ', is less than the minimum of 1');
		}
		
		switch ($type) {
			case 'alphanumeric':
				$alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
				break;
				
			case 'alpha':
				$alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';	
				break;
				
			case 'numeric':
				$alphabet = '0123456789';
				break;
				
			case 'hexadecimal':
				$alphabet = 'abcdef0123456789';
				break;
				
			default:
				fCore::toss('fProgrammerException', 'Invalid string type, ' . $type . ', specified');
				break;
		}
		
		$alphabet_length = strlen($alphabet);
		$output = '';
		
		for ($i = 0; $i < $length; $i++) {
			$output .= $alphabet[rand(0, $alphabet_length-1)];	
		}
		
		return $output;
	}
	
	
	/**
	 * Hashes a password using a loop of sha1 hashes and a salt, making rainbow table attacks infeasible
	 * 
	 * @param  string $password  The password to hash
	 * @return string  A 64 character string of the password hash
	 */
	static public function hashPassword($password)
	{
		$salt = self::generateRandomString(5);

		return self::hashWithSalt($password, $salt);
	}
	
	
	/**
	 * Checks a password against a hash
	 * 
	 * @param  string $password  The password to check
	 * @param  string $hash      The hash to check against
	 * @return boolean  If the password matches the hash
	 */
	static public function checkPasswordHash($password, $hash)
	{
		$salt = substr($hash, 20, 5);

		if (self::hashWithSalt($password, $salt) == $hash) {
			return TRUE;	
		}
		
		return FALSE;
	}
	
	
	/**
	 * Performs a large iteration of hashing a string with a salt
	 * 
	 * @param  string $source  The string to hash
	 * @param  string $salt    The salt for the hash
	 * @return string  The hash of the source, a 64 character string
	 */
	static private function hashWithSalt($source, $salt)
	{
		$sha1 = sha1($salt . $source);
		for ($i = 0; $i < 1000; $i++) {
			$sha1 = sha1($sha1 . (($i % 2 == 0) ? $source : $salt));
		}  
		
		return 'fCryptography::hash#' . $salt . '#' . $sha1;
	}
	
	
	/**
	 * Encrypts the passed data using symmetric-key encryption. Thus the same key is used for encrypting and decrypting data.
	 * 
	 * @param  string $plaintext   The content to be encrypted
	 * @param  string $secret_key  The secret key to use for encryption
	 * @return string  An encrypted and base-64 encoded result containing a Flourish fingerprint and suitable for decryption using {@link symmetricKeyDecrypt()}
	 */
	static public function symmetricKeyEncrypt($plaintext, $secret_key)
	{
		if (strlen($secret_key) < 8) {
			fCore::toss('fProgrammerException', 'The secret key specified does not meet the minimum requirement of being at least 8 characters long');   
		}
		
		self::verifySymmetricKeyEnvironment();
		
		// Set up the main encryption, we are gonna use AES-256 (also know as rijndael-256)
		// in cipher feedback mode. Cipher feedback mode is chosen because no extra padding
		// is added, ensuring we always get the exact same plaintext out of the decrypt method
		$module   = mcrypt_module_open('rijndael-256', '', 'cfb', '');
		$key      = substr($secret_key, 0, mcrypt_enc_get_key_size($module));
		srand();
		$iv       = mcrypt_create_iv(mcrypt_enc_get_iv_size($module), MCRYPT_RAND);

		// Encrypt the IV for storage to prevent man in the middle attacks. This uses
		// electronic codebook since it is suitable for encrypting the IV. 
		$iv_module = mcrypt_module_open('tripledes', '',  'ecb', '');
		$iv_key    = substr($secret_key, 0, mcrypt_enc_get_key_size($iv_module));
		mcrypt_generic_init($iv_module, $iv_key, NULL);
		$encrypted_iv = mcrypt_generic($iv_module, $iv);
		mcrypt_generic_deinit($iv_module);
		mcrypt_module_close($iv_module);

		// Finish the main encryption
		mcrypt_generic_init($module, $key, $iv);
		$ciphertext = mcrypt_generic($module, $plaintext);

		// Clean up the main encryption
		mcrypt_generic_deinit($module);
		mcrypt_module_close($module);
		
		// Here we are generating the HMAC for the encrypted data to ensure data integrity
		$hmac = hash_hmac('sha256', $encrypted_iv . $ciphertext, $secret_key);
		
		// All of the data is then encoded using base64 to prevent issues with character sets
		$encoded_iv         = base64_encode($encrypted_iv);
		$encoded_ciphertext = base64_encode($ciphertext);
		$encoded_hmac       = base64_encode($hmac);
		
		// Indicate in the resulting encrypted data what the encryption tool was
		return 'fCyptography::symmetric#' . $encoded_iv . '#' . $encoded_ciphertext . '#' . $encoded_hmac;
	}
	
	
	/**
	 * Decrypts ciphertext encrypted using symmetric-key encryption via {@link symmetricKeyEncrypt()}. Thus the same key is used for encrypting and decrypting data.
	 * 
	 * @throws  fValidationException
	 * 
	 * @param  string $ciphertext  The content to be decrypted
	 * @param  string $secret_key  The secret key to use for decryption
	 * @return string  The decrypted plaintext
	 */
	static public function symmetricKeyDecrypt($ciphertext, $secret_key)
	{
		self::verifySymmetricKeyEnvironment();
		
		$elements = explode('#', $ciphertext);
		
		// We need to make sure this ciphertext came from here, otherwise we are gonna have issues decrypting it
		if (sizeof($elements) != 4 || $elements[0] != 'fCryptography::symmetric') {
			fCore::toss('fProgrammerException', 'The ciphertext provided does not appear to have been encrypted using fCryptography::symmetricKeyEncrypt()');	
		}
		
		$encrypted_iv  = base64_decode($elements[1]);
		$ciphertext    = base64_decode($elements[2]);
		$provided_hmac = base64_decode($elements[3]);
		
		$hmac = hash_hmac('sha256', $encrypted_iv . $ciphertext, $secret_key);
		
		// By verifying the HMAC we ensure the integrity of the data
		if ($hmac != $provided_hmac) {
		fCore::toss('fValidationException', 'The ciphertext provided appears to have been tampered with or corrupted');	
		}
		
		// Decrypt the IV so we can feed it into the main decryption
		$iv_module = mcrypt_module_open('tripledes', '',  'ecb', '');
		$iv_key    = substr($secret_key, 0, mcrypt_enc_get_key_size($iv_module));
		mcrypt_generic_init($iv_module, $iv_key, NULL);
		$iv        = mdecrypt_generic($iv_module, $iv);
		mcrypt_generic_deinit($iv_module);
		mcrypt_module_close($iv_module);
		
		// Set up the main encryption, we are gonna use AES-256 (also know as rijndael-256) in cipher feedback mode
		$module   = mcrypt_module_open('rijndael-256', '', 'cfb', '');
		$key      = substr($secret_key, 0, mcrypt_enc_get_key_size($module));
		mcrypt_generic_init($module, $key, $iv);
		$plaintext = mdecrypt_generic($module, $ciphertext);
		mcrypt_generic_deinit($module);
		mcrypt_module_close($module);
		
		return $plaintext;
	}
	
	
	/**
	 * Makes sure the required PHP extensions and library versions are all correct
	 * 
	 * @return void
	 */
	static private function verifySymmetricKeyEnvironment()
	{
		if (!extension_loaded('mcrypt')) {
			fCore::toss('fEnvironmentException', 'The PHP mcrypt extension is required, however is does not appear to be loaded');    
		}
		if (!extension_loaded('hash')) {
			fCore::toss('fEnvironmentException', 'The PHP hash extension is required, however is does not appear to be loaded');    
		}
		if (!function_exists('mcrypt_module_open')) {
			fCore::toss('fEnvironmentException', 'The cipher used, AES-256 (also known as rijndael-256), requires libmcrypt version 2.4.x or newer. The version installed does not appear to meet this requirement.');
		}	
		if (!in_array('rijndael-256', mcrypt_list_algorithms())) {
		fCore::toss('fEnvironmentException', 'The cipher used, AES-256 (also known as rijndael-256), does not appear to be supported by the installed version of libmcrypt');
		}
	}
	
	
	/**
	 * Encrypts the passed data using public key encryption via OpenSSL. A public key (X.509 certificate) is required for encryption and a private key (PEM) is required for decryption.
	 * 
	 * @param  string $plaintext        The content to be encrypted
	 * @param  string $public_key_file  The path to an X.509 public key certificate
	 * @return string  A base-64 encoded result containing a Flourish fingerprint and suitable for decryption using {@link symmetricKeyDecrypt()}
	 */
	static public function publicKeyEncrypt($plaintext, $public_key_file)
	{
		self::verifyPublicKeyEnvironment();   
		
		if (!file_exists($public_key_file)) {
			fCore::toss('fProgrammerException', 'The path to the X.509 certificate specified is not valid');	
		}
		if (!is_readable($public_key_file)) {
		fCore::toss('fProgrammerException', 'The X.509 certificate specified can not be read');	
		}
		
		$public_key = file_get_contents($public_key_file);
		$public_key_resource = openssl_pkey_get_public($public_key);
		
		$ciphertext = '';
		openssl_public_encrypt($plaintext, $ciphertext, $public_key_resource);
		openssl_free_key($public_key_resource);
		
		return 'fCryptography::public#' . base64_encode($ciphertext);
	}
	
	
	/**
	 * Decrypts ciphertext encrypted using public-key encryption via {@link publicKeyEncrypt()}. A public key (X.509 certificate) is required for encryption and a private key (PEM) is required for decryption.
	 * 
	 * @throws  fValidationException
	 * 
	 * @param  string $ciphertext        The content to be decrypted
	 * @param  string $private_key_file  The path to a PEM-encoded private key
	 * @param  string $password          The password for the private key
	 * @return string  The decrypted plaintext
	 */
	static public function publicKeyDecrypt($ciphertext, $private_key_file, $password)
	{
		self::verifyPublicKeyEnvironment();	
		
		if (!file_exists($private_key_file)) {
			fCore::toss('fProgrammerException', 'The path to the PEM-encoded private key specified is not valid');	
		}
		if (!is_readable($private_key_file)) {
			fCore::toss('fProgrammerException', 'The PEM-encoded private key specified can not be read');	
		}
		
		$private_key = file_get_contents($private_key_file);
		$private_key_resource = openssl_pkey_get_private($private_key);
		
		if ($private_key_resource === FALSE) {
			fCore::toss('fValidationException', 'The private key password specified does not appear to be valid for the private key specified');	
		}
		
		$elements = explode('#', $ciphertext);
		
		// We need to make sure this ciphertext came from here, otherwise we are gonna have issues decrypting it
		if (sizeof($elements) != 2 || $elements[0] != 'fCryptography::public') {
			fCore::toss('fProgrammerException', 'The ciphertext provided does not appear to have been encrypted using fCryptography::publicKeyEncrypt()');	
		}
		
		$ciphertext = base64_decode($elements[1]);
		
		$plaintext = '';
		openssl_public_decrypt($ciphertext, $plaintext, $private_key_resource);
		openssl_free_key($private_key_resource);
		
		return $plaintext;
	}
	
	
	/**
	 * Makes sure the required PHP extensions and library versions are all correct
	 * 
	 * @return void
	 */
	static private function verifyPublicKeyEnvironment()
	{
		if (!extension_loaded('openssl')) {
			fCore::toss('fEnvironmentException', 'The PHP openssl extension is required, however is does not appear to be loaded');    
		}
	}
} 


/**
 * Copyright (c) 2007-2008 William Bond <will@flourishlib.com>
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */ 
?>