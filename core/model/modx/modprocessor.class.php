<?php
/**
 * modProcessor
 *
 * @package modx
 */
/**
 * Abstracts a MODX processor
 *
 * {@inheritdoc}
 *
 * @package modx
 */
class modProcessor {
    public $modx = null;
    public $path = '';
    public $properties = array();

    /**
     * Creates a modProcessor object.
     *
     * {@inheritdoc}
     */
    function __construct(modX & $modx,array $config = array()) {
        $this->modx =& $modx;
    }

    public function setPath($path) {
        $this->path = $path;
    }
    public function setProperties($properties) {
        $this->properties = $properties;
    }

    public function run() {
        $modx =& $this->modx;
        $scriptProperties = $this->properties;
        $o = include $this->path;
        $response = new modProcessorResponse($this->modx,$o);
        return $response;
    }

    /**
     * Return arrays of objects (with count) converted to JSON.
     *
     * The JSON result includes two main elements, total and results. This format is used for list
     * results.
     *
     * @access public
     * @param array $array An array of data objects.
     * @param mixed $count The total number of objects. Used for pagination.
     * @return string The JSON output.
     */
    public function outputArray(array $array,$count = false) {
        if (!is_array($array)) return false;
        if ($count === false) { $count = count($array); }
        return '({"total":"'.$count.'","results":'.$this->modx->toJSON($array).'})';
    }

    /**
     * Converts PHP to JSON with JavaScript literals left in-tact.
     *
     * JSON does not allow JavaScript literals, but this function encodes certain identifiable
     * literals and decodes them back into literals after modX::toJSON() formats the data.
     *
     * @access public
     * @param mixed $data The PHP data to be converted.
     * @return string The extended JSON-encoded string.
     */
    public function toJSON($data) {
        if (is_array($data)) {
            array_walk_recursive($data, array(&$this, '_encodeLiterals'));
        }
        return $this->_decodeLiterals($this->modx->toJSON($data));
    }

    /**
     * Encodes certain JavaScript literal strings for later decoding.
     *
     * @access protected
     * @param mixed &$value A reference to the value to be encoded if it is identified as a literal.
     * @param integer|string $key The array key corresponding to the value.
     */
    protected function _encodeLiterals(&$value, $key) {
        if (is_string($value)) {
            /* properly handle common literal structures */
            if (strpos($value, 'function(') === 0
             || strpos($value, 'this.') === 0
             || strpos($value, 'new Function(') === 0
             || strpos($value, 'Ext.') === 0) {
                $value = '@@' . base64_encode($value) . '@@';
             }
        }
    }

    /**
     * Decodes strings encoded by _encodeLiterals to restore JavaScript literals.
     *
     * @access protected
     * @param string $string The JSON-encoded string with encoded literals.
     * @return string The JSON-encoded string with literals restored.
     */
    protected function _decodeLiterals($string) {
        $pattern = '/"@@(.*?)@@"/';
        $string = preg_replace_callback(
            $pattern,
            create_function('$matches', 'return base64_decode($matches[1]);'),
            $string
        );
        return $string;
    }

    /**
     * Processes a response from a Plugin Event invocation
     *
     * @param array/string $response The response generated by the invokeEvent call
     * @param string $separator
     * @return string The processed response.
     */
    public function processEventResponse($response,$separator = "\n") {
        if (is_array($response)) {
            $result = false;
            foreach ($response as $msg) {
                if (!empty($msg)) {
                    $result[] = $msg;
                }
            }
            $result = implode($separator,$result);
        } else {
            $result = $response;
        }
        return $result;
    }
}

/**
 * Response class for Processor executions
 */
class modProcessorResponse {
    /**
     * When there is only a general error
     */
    const ERROR_GENERAL = 'error_general';
    /**
     * When there are only field-specific errors
     */
    const ERROR_FIELD = 'error_field';
    /**
     * When there is both field-specific and general errors
     */
    const ERROR_BOTH = 'error_both';
    /**
     * The field for the error type
     */
    const ERROR_TYPE = 'error_type';

    /**
     * @var modX A reference to the modX object
     */
    public $modx = null;
    /**
     * @var array A reference to the full response
     */
    public $response = null;
    /**
     * @var array A collection of modProcessorResponseError objects for each field-specific error
     */
    public $errors = array();
    /**
     * @var string The error type for this response
     */
    public $error_type = '';

    /**
     * The constructor for modProcessorResponse
     *
     * @param modX $modx A reference to the modX object.
     * @param array $response The array response from the modX.runProcessor method
     */
    function __construct(modX &$modx,$response = array()) {
        $this->modx =& $modx;
        $this->response = $response;
        if ($this->isError()) {
            if (!empty($response['errors']) && is_array($response['errors'])) {
                foreach ($response['errors'] as $error) {
                    $this->errors[] = new modProcessorResponseError($error);
                }
                if (!empty($response['message'])) {
                    $this->error_type = modProcessorResponse::ERROR_BOTH;
                } else {
                    $this->error_type = modProcessorResponse::ERROR_FIELD;
                }
            } else {
                $this->error_type = modProcessorResponse::ERROR_GENERAL;
            }
        }
    }

    /**
     * Returns the type of error for this response
     * @return string The type of error returned
     */
    public function getErrorType() {
        return $this->error_type;
    }

    /**
     * Checks to see if the response is an error
     * @return Returns true if the response was a success, otherwise false
     */
    public function isError() {
        return empty($this->response) || empty($this->response['success']);
    }

    /**
     * Returns true if there is a general status message for the response.
     * @return boolean True if there is a general message
     */
    public function hasMessage() {
        return !empty($this->response['message']) ? true : false;
    }

    /**
     * Gets the general status message for the response.
     * @return string The status message
     */
    public function getMessage() {
        return !empty($this->response['message']) ? $this->response['message'] : '';
    }

    /**
     * Returns the entire response object in array form
     * @return array The array response
     */
    public function getResponse() {
        return $this->response;
    }

    /**
     * Returns true if an object was sent with this response.
     * @return boolean True if an object was sent.
     */
    public function hasObject() {
        return !empty($this->response['object']) ? true : false;
    }

    /**
     * Returns the array object, if is sent in the response
     * @return array The object in the response, usually the object being performed on.
     */
    public function getObject() {
        return !empty($this->response['object']) ? $this->response['object'] : array();
    }

    /**
     * An array of modProcessorResponseError objects for each field-specific error
     * @return array
     */
    public function getFieldErrors() {
        return $this->errors;
    }

    /**
     * Checks to see if there are any field-specific errors in this response
     * @return boolean True if there were field-specific errors
     */
    public function hasFieldErrors() {
        return !empty($this->errors) ? true : false;
    }

    /**
     * Gets all errors and adds them all into an array.
     *
     * @param string $fieldErrorSeparator The separator to use between fieldkey and message for field-specific errors.
     * @return array An array of all errors.
     */
    public function getAllErrors($fieldErrorSeparator = ': ') {
        $errormsgs = array();
        if ($this->response->hasMessage()) {
            $errormsgs[] = $this->response->getMessage();
        }
        if ($this->response->hasFieldErrors()) {
            $errors = $this->response->getFieldErrors();
            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $errormsgs[] = $error->field.$fieldErrorSeparator.$error->message;
                }
            }
        }
        return $errormsgs;
    }
}
/**
 * An abstraction class of field-specific errors for a processor response
 */
class modProcessorResponseError {
    /**
     * @var array The error data itself
     */
    public $error = null;
    /**
     * @var string The field key that the error occurred on
     */
    public $field = null;
    /**
     * @var string The message that was sent for the field error
     */
    public $message = '';

    /**
     * The constructor for the modProcessorResponseError class
     *
     * @param array $error An array error response
     */
    function __construct($error = array()) {
        $this->error = $error;
        if (!empty($error['id'])) { $this->field = $error['id']; }
        if (!empty($error['msg'])) { $this->message = $error['msg']; }
    }

    /**
     * Returns the message for the field-specific error
     * @return string
     */
    public function getMessage() {
        return $this->message;
    }

    /**
     * Returns the field key for the field-specific error
     */
    public function getField() {
        return $this->field;
    }

    /**
     * Returns the array data for the field-specific error
     * @return array
     */
    public function getError() {
        return $this->error;
    }
}