<?php

if (!extension_loaded('ast')) {
    die("This script needs the ast extension. See https://github.com/nikic/php-ast");
}

if (isset($argv[1])) {
    $file = $argv[1];
} else {
    $file = __FILE__;
}

print "Reading aloud the file $file into out.txt\n";

$x = new toEnglish(ast\parse_code(file_get_contents($file), $version=50));
$text = $x->render('out.txt');
file_put_contents('out.txt', $text);

echo count(explode(' ', $text)).' words'.PHP_EOL;

class toEnglish {
    private const SILENT = true, TYPED = false;

    private $ast = null;
    private $node = null;
    
    public function __construct($ast) {
        $this->ast = $ast;
    }

    function render() {
        return 'First, the script starts.'.PHP_EOL.$this->renderIt($this->ast).'. '.PHP_EOL.'Then the script ends.'.PHP_EOL;
    }
    
    function renderIt($node, $silent = self::TYPED) {
        if (is_integer($node)) {
            if ($silent === true) {
                return $this->integer2English($node);
            } else {
                return 'the integer '.$this->integer2English($node);
            }
        } elseif (is_string($node)) {
            if ($silent === true) {
                return $node;
            } else {
                return 'the string "'.$node.'"';
            }
        } elseif (is_real($node)) {
            if ($silent === true) {
                return $node;
            } else {
                return 'the double "'.$node.'"';
            }
        }

        if (!$node instanceof ast\Node) {
            print "Renderit called with ".gettype($node);
            debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        };
        $method = $this->get_read_from_kind($node->kind);
        assert(!empty($method), "Missing method for $node->kind from method naming\n");
        
        if (!method_exists($this, $method) ) {
            print_r($node);
            die("Missing method function $method(\$node) { }\n");
        }
        
        $text = $this->$method($node);
        
        return $text; 
    }
    
    function get_read_from_kind($kind) {
        static $kinds;
        
        if ($kinds === null) {
            $kinds = get_defined_constants(true)['ast'];
            $kinds = array_filter($kinds, function($x) { return strpos($x, 'flags') === false; }, ARRAY_FILTER_USE_KEY);
            $kinds = array_flip($kinds);
            $kinds = array_map(function($x) { return strtolower(str_replace('ast\\', 'read_', $x)); }, $kinds);
        }
        
        assert(isset($kinds[$kind]), "Missing offset $kind from method naming\n");
        return $kinds[$kind];
    }
    
    function read_ast_stmt_list($node) {
        $return = array();

        foreach($node->children as $child) {
            $return[] = 'It does '.$this->renderIt($child);
        }
        
        $return = implode('. '.PHP_EOL, $return);

        return $return;
    }
    
    function read_ast_const_decl($node) {
        $c = count($node->children);
        $return = $c.' constant'.($c > 1 ? 's' : '').'. ';
        
        foreach($node->children as $children) {
            $return .= $this->renderIt($children);
        }
        return $return;
    }
    
    function read_ast_const_elem($node) { 
        if ($node->children['value'] instanceof ast\Node) {
            $value = $this->renderIt($node->children['value']);
        } else {
            $value = $node->children['value'];
        }
        $return = 'a constant named '.$node->children['name'].', which gets the value of '.$value;

        return $return;
    }

    function read_ast_binary_op($node) {
        $operations = array(ast\flags\BINARY_ADD                 => ' plus ',
                            ast\flags\BINARY_SUB                 => ' minus ',
                            ast\flags\BINARY_MUL                 => ' multiplied by ',
                            ast\flags\BINARY_DIV                 => ' divided by ',
                            ast\flags\BINARY_CONCAT              => ' concatenated with ',
                            ast\flags\BINARY_IS_GREATER_OR_EQUAL => ' is greater or equal to ',
                            ast\flags\BINARY_IS_SMALLER_OR_EQUAL => ' is smaller or equal to ',
                            ast\flags\BINARY_IS_IDENTICAL        => ' is identical to ',
                            ast\flags\BINARY_IS_NOT_IDENTICAL    => ' is not identical to ',
                            ast\flags\BINARY_IS_SMALLER          => ' is smaller than ',
                            ast\flags\BINARY_IS_EQUAL            => ' is equal to ',
                            ast\flags\BINARY_IS_NOT_EQUAL        => ' is not equal to ',
                            ast\flags\BINARY_IS_GREATER          => ' is greater than ',
                            ast\flags\BINARY_BITWISE_AND         => ' binarily combined by and with ',
                            ast\flags\BINARY_BITWISE_OR          => ' binarily combined by or with ',
                            ast\flags\BINARY_BITWISE_XOR         => ' binarily combined by xor with ',
                            ast\flags\BINARY_BOOL_OR             => ' combined by or with ',
                            ast\flags\BINARY_BOOL_XOR            => ' combined by xor with ',
                            ast\flags\BINARY_BOOL_AND            => ' combined by and with ',
                            ast\flags\BINARY_MOD                 => ' modulo ',
                            ast\flags\BINARY_SHIFT_LEFT          => ' shift bits to the left by ',
                            ast\flags\BINARY_SHIFT_RIGHT          => ' shift bits to the right by ',
                            );

        if (!isset($operations[$node->flags])) {
            print_r($node);
        }
        $operation = $operations[$node->flags];
        $left = $this->renderIt($node->children['left']);
        $right = $this->renderIt($node->children['right']);

        return $left . $operation . $right;
    }

    function read_ast_const($node) {
        $name = $this->renderIt($node->children['name']);
        
        if (strtolower($name) === 'true') {
            return 'boolean true';
        } elseif (strtolower($name) === 'false') {
            return 'boolean false';
        } elseif (strtolower($name) === 'null') {
            return 'null';
        } else {
            return 'the constant ' .$name;
        }
    }

    function read_ast_name($node) {
        return $node->children['name'];
    }
    
    // Assignation
    function read_ast_assign($node) {
        $var = $this->renderIt($node->children['var']);
        $expression = $this->renderIt($node->children['expr']);
        
        return 'the assignation of '.$expression.' to '.$var;
    }

    function read_ast_assign_op($node) { 
        $var = $this->renderIt($node->children['var']);
        $expr = $this->renderIt($node->children['expr']);
        
        $operations = array(ast\flags\BINARY_CONCAT     => ' appended with ',
                            ast\flags\BINARY_BITWISE_OR => ' combined by or with ',
                            ast\flags\BINARY_BITWISE_AND=> ' combined by and with ',
                            ast\flags\BINARY_BITWISE_XOR=> ' combined by xor with ',
                            ast\flags\BINARY_ADD=> ' added with ',
                            ast\flags\BINARY_SUB=> ' substracted with ',
                            ast\flags\BINARY_MUL=> ' multiplied with ',
                            ast\flags\BINARY_DIV=> ' divided by ',
                            ast\flags\BINARY_MOD=> ' reduced to remaining of ',
                            ast\flags\BINARY_POW=> ' raised to the power of ',
                            ast\flags\BINARY_SHIFT_LEFT=> ' with bits shifted on the left by ',
                            ast\flags\BINARY_SHIFT_RIGHT=> ' with bits shifted on the left by ',
                            );

        return $expr .' is '.$operations[$node->flags].' the value of '.$var;
    }
    
    function read_ast_var(ast\Node $node) { 
        if ($node->children['name'] instanceof ast\Node) {
            return '$'.$this->renderIt($node->children['name']);
        } else {
            return '$'.$node->children['name'];
        }
    }

    // New 
    function read_ast_new($node) {
        if (isset($node->children['class'])) {
            $class = $this->renderIt($node->children['class']);
        } else {
            print_r($node);
            die('Missing value for class');
        }

        if (isset($node->children['args'])) {
            $arguments = $this->renderIt($node->children['args']);
        } else {
            print_r($node);
            die('Missing value for args');
        }
        
        return 'an instantiation of the class '.$class.', built '.$arguments;
    }

    // Argument list
    function read_ast_arg_list($node) { 
        $c = count($node->children);
        if ($c === 0) {
            return 'without any argument';
        }

        if ($c === 1) {
            return 'with only one argument, '.$this->renderIt($node->children[0]).', ';
        }

        $c = count($node->children);
        $return = 'with a list of '.$this->integer2English($c).' argument'.($c > 1 ? 's' : '').', consisting of ';
        
        $arguments = $this->renderList($node->children);

        return $return.$arguments;
    }

    function read_ast_call($node) { 
        $name = $this->renderIt($node->children['expr']);
        
        $method = 'function_'.strtolower($name);
        if (method_exists($this, $method)) {
            return $this->$method($node->children['args']);
        } else {
            static $set;
            
            if (!isset($set[$method])) {    
                // This is lazy dev to detecting undocumented methods
                <<<PHP
    function $method(\$args) {
//        \$a = \$this->renderIt(\$args->children[0], self::SILENT);
//        \$b = \$this->renderIt(\$args->children[1], self::SILENT);
//        \$c = \$this->renderIt(\$args->children[2], self::SILENT);
        return ' a = '.\$a;
    }


PHP;
                $set[$method] = 1;
            }
            $args = $this->renderIt($node->children['args']);
            $return = 'a call to the function '.$name.' '.$args;
            return $return;
        }
    }

    function read_ast_print($node) { 
        $arguments = $this->renderIt($node->children['expr']);

        return 'a display to the stdout of the value of '.$arguments;
    }

    function read_ast_method_call($node) { 
        $object = $this->renderIt($node->children['expr']);
        $method = $this->renderIt($node->children['method'], self::SILENT);
        $arguments = $this->renderIt($node->children['args']);

        return 'a call to the method '.$method.' of the object '.$object.', '.$arguments;
    }

    function read_ast_func_decl($node) { 
        $stmts = $this->renderIt($node->children['stmts']);
        $name = $this->renderIt($node->children['name']);
        if ($node->children['uses'] !== null) {
            $uses = $this->renderIt($node->children['uses']);
        }
        $params = $this->renderIt($node->children['params']);

        return 'the definition of the function called "'.$name.'" with the parameters '.$params.'and executing the following expressions : '.$stmts;
    }

    function read_ast_prop($node) { 
        $object = $this->renderIt($node->children['expr']);
        $property = $this->renderIt($node->children['prop'], self::SILENT);

        return 'the property '.$property.' of the object '.$object;
    }

    function read_ast_param_list($node) { 
        return $this->read_ast_arg_list($node);
    }

    function read_ast_param($node) { 
        if ($node->children['type'] !== null) {
            $type = $this->renderIt($node->children['type']);
        }
        $name = $this->renderIt($node->children['name'], self::SILENT);
        if ($node->children['default'] !== null) {
            $default = $this->renderIt($node->children['default']);
        }

        return 'a parameter named '.$name.(isset($default) ? ' with a default value of '.$default : '')
                                          .(isset($type) ? ' with a type of '.$type : '');
    }

    function read_ast_return($node) { 
        if ($node->children['expr'] === null) {
            return 'the return of void';
        }
        $expr = $this->renderIt($node->children['expr']);

        return 'the return of the value of '.$expr;
    }

    function read_ast_class($node) { 
        if ($node->children['extends'] !== null) {
            $extends = $this->renderIt($node->children['extends']);
        }
        if ($node->children['implements'] !== null) {
            $implements = $this->renderIt($node->children['implements']);
        }
        $name = $this->renderIt($node->children['name'], self::SILENT);

        if (!empty($node->children['stmts']->children)) {
            $stmts = [];
            foreach($node->children['stmts']->children as $child) {
                $stmts[] = 'The class '.$name.' declares '.$this->renderIt($child);
            }
            $stmts = PHP_EOL.implode('. '.PHP_EOL, $stmts);
            $c = count($node->children['stmts']->children);
        }
        
        return 'the definition of the class named '.$name
                        .(isset($extends)    ? ' which extends '.$extends . ' ' : '')
                        .(isset($implements) ? ' which implements '.$implements. ' ' : '')
                        .(isset($stmts)      ? ' which defines '.$this->integer2English($c).' element'.($c > 1 ? 's' : '').'. '.$stmts : ' which defines no elements');
    }

    function read_ast_name_list($node) {
        $return = '';
        
        $names = array_map(array($this, 'renderIt'), $node->children);
        if (count($names) == 1) {
            return $names[0];
        }

        $last = array_pop($names);
        $secondToLast = array_pop($names);
        $last = $secondToLast.' and '.$last;
        $names[] = $last;
        
        return implode(', ', $names);
    }

    // Property declaration
    function read_ast_prop_decl($node) { 
        $c = count($node->children);
        $return = $this->integer2English($c).' propert'.($c > 1 ? 'ies' : 'y').' : ';
        
        $propertyList = $this->renderList($node->children);
        
        $return .= $propertyList;

        return $return;
    }
    
    function read_ast_prop_elem($node) { 
        $name = $this->renderIt($node->children['name'], self::SILENT);
        if ($node->children['default'] !== null) {
            $default = $this->renderIt($node->children['default']);
        }
        
        return 'a property with name '.$name.(isset($default) ? ' and a default value of '.$default : '');
    }

    function read_ast_method($node) { 
        $private = $node->flags & ast\flags\MODIFIER_PRIVATE;
        $protected = $node->flags & ast\flags\MODIFIER_PROTECTED;
        $public = $node->flags & ast\flags\MODIFIER_PUBLIC;

        $name = $this->renderIt($node->children['name'], self::SILENT);
        $params = $this->renderIt($node->children['params']);
        if (!empty($node->children['stmts']->children)) {
            
            $stmts = $this->renderIt($node->children['stmts']);
            $c = count($node->children['stmts']->children);
            $block = 'It executes '.$this->integer2English($c).' expression'.($c > 1 ? 's' : '').'. '.$stmts;
        } else {
            $block = 'It doesn\'t execute anything';
        }

        return 'the definition of a '
            .($private == true ? 'private ' : '' )
            .($protected == true ? 'protected ' : '' )
            .($public == true ? 'public ' : '' )
            .'method called '.$name.' '.$params.'. '.$block;
    }

    function read_ast_if($node) {
        $return = '';
        
        foreach($node->children as $child) {
            $return .= $this->renderIt($child).' and ';
        }
        $return = substr($return, 0, -5);
        
        return $return;
    }

    function read_ast_if_elem($node) { 
        if ($node->children['cond'] !== null) {
            $cond = $this->renderIt($node->children['cond']);
        }
        $stmts = $this->renderIt($node->children['stmts']);
        
        if (isset($cond)) {
            return ', upon the validation of '.$cond.', the following : '.$stmts;
        } else {
            return ', otherwise, the following : '.$stmts;
        }
   }

    function read_ast_instanceof($node) { 
        $expr = $this->renderIt($node->children['expr']);
        $class = $this->renderIt($node->children['class']);
        
        return 'the verification that ' .$expr.' is an instance of the class '.$class;
    }

    function read_ast_unary_op($node) { 
        $operations = array(ast\flags\UNARY_BOOL_NOT    => 'not ',
                            ast\flags\UNARY_BITWISE_NOT => 'not ',
                            ast\flags\UNARY_MINUS       => 'minus ',
                            ast\flags\UNARY_SILENCE       => 'noscream ',
                            ast\flags\UNARY_PLUS       => 'plus ',
                            
                             );

        $operation = $operations[$node->flags];
        $expr = $this->renderIt($node->children['expr']);

        return $operation . $expr;
    }

    function read_ast_empty($node) { 
        $expr = $this->renderIt($node->children['expr']);
        
        return 'how empty is '.$expr;
    }
    
    function read_ast_encaps_list($node) { 
        $return = array();

        foreach($node->children as $child) {
            $return[] = $this->renderIt($child).' and ';
        }
        
        return 'a string, made of the following '.count($return). ' elements : '.implode(', ', $return);
    }
    
    function read_ast_exit($node) { 
        if ($node->children['expr'] !== null) {
            $expr = $this->renderIt($node->children['expr']);
        }

        return 'the exit of the script '.(isset($expr) ? 'with '.$expr : '').'. ';
    }

    function read_ast_static($node) { 
       $var = $this->renderIt($node->children['var']);
        if ($node->children['default'] !== null) {
            $default = $this->renderIt($node->children['default']);
        }

        return 'it declares the static variables '.$var.
                (isset($default) ? ' with a default value of '.$default.' ' : '') .
                    '. ';
    }

    function read_ast_dim($node) { 
       $expr = $this->renderIt($node->children['expr']);
        if ($node->children['dim'] !== null) {
           $dim = $this->renderIt($node->children['dim']);
            return 'the element at index '.$dim.' in the array '.$expr;
        } else {
            return 'the appending to the array '.$expr;
        }
    }

     function read_ast_closure($node) { 
        $stmts = $this->renderIt($node->children['stmts']);
        $params = $this->renderIt($node->children['params']);
        if ($node->children['uses'] !== null) {
            $uses = $this->renderIt($node->children['uses']);
        }

        return 'the closure '.$params.
                (isset($uses) ? ' which uses the context variables '.$uses.' ' : '').
                'and executing the following expressions : '.$stmts;
     }

    function read_ast_isset($node) { 
        $var = $this->renderIt($node->children['var']);
        return 'a check on the existence of '.$var.' ';
    }

    function read_ast_conditional($node) { 
        $cond = $this->renderIt($node->children['cond']);
        $true = $this->renderIt($node->children['true']);
        $false = $this->renderIt($node->children['false']);

        return 'if '.$cond.' then '.$true.' or else '.$false.' ';
    }

    function read_ast_foreach($node) {
        $expr = $this->renderIt($node->children['expr']);
        if ($node->children['key'] !== null) {
            $key = $this->renderIt($node->children['key']);
        }
        $value = $this->renderIt($node->children['value']);
        $stmts = $this->renderIt($node->children['stmts']);

        return 'a loop covering '.$expr.' by checking each element in '.(isset($key) ? $key.' and ' : '').$value.' and executing the following statements : '.$stmts.' ';
    }

    function read_ast_array($node) { 
        $c = count($node->children);
        
        if ($c === 0) {
            return 'an empty array';
        }
        
        $elements = array();
        foreach($node->children as $child) {
            $elements[] = $this->renderIt($child);
        }
        
        return 'an array with '.$this->integer2English($c).' element, being the following : '.implode('; then, ', $elements);
    }

    function read_ast_array_elem($node) { 
        $value = $this->renderIt($node->children['value']);
        if ($node->children['key'] !== null) {
            $key = $this->renderIt($node->children['key']);
        }

        return 'the value '.$value.(isset($key) ? ' with the key '.$key.' ' : '').' ';
    }
    
    function read_ast_class_const($node) { 
        $class = $this->renderIt($node->children['class']);
        $key = $this->renderIt($node->children['const']);

        return 'the constant '.$key.' from the class '.$class.' ';
    }

    private function integer2English($integer) {
        $nf = new NumberFormatter('en', NumberFormatter::SPELLOUT);
        
        return $nf->format($integer);
    }

    function read_ast_class_const_decl($node) { 
        $c = count($node->children);
        $constantsList = $this->renderList($node->children);

        return $this->integer2English($c).' class constant'.($c > 1 ? 's' : '').' : ' . $constantsList;
    }
    
    private function renderList($array) {
        if (count($array) === 0) {
            return '';
        }

        if (count($array) === 1) {
            return $this->renderIt($array[0]);
        }

        $elements = array();
        foreach($array as $child) {
            $elements[] = $this->renderIt($child);
        }
        
        if (count($elements) > 3) {
            $elements[0] = 'first, '.$elements[0];
        }

        $last = array_pop($elements);
        $secondToLast = array_pop($elements);
        $elements[] = $secondToLast . ' and, '.(count($elements) > 3 ? ' finally, ' : '' ).$last;
        
        return implode('; ', $elements);
    }

    function read_ast_pre_inc($node) { 
        $var = $this->renderIt($node->children['var']);

        return 'the pre-incrementation of '.$var;
    }

    function read_ast_pre_dec($node) { 
        $var = $this->renderIt($node->children['var']);

        return 'the pre-decrementation of '.$var;
    }

    function read_ast_cast($node) { 
        $expr = $this->renderIt($node->children['expr']);

        $casts = array(ast\flags\TYPE_NULL     => 'null',
                       ast\flags\TYPE_BOOL     => 'boolean',
                       ast\flags\TYPE_LONG     => 'long',
                       ast\flags\TYPE_DOUBLE   => 'double',
                       ast\flags\TYPE_STRING   => 'string',
                       ast\flags\TYPE_ARRAY    => 'array',
                       ast\flags\TYPE_OBJECT   => 'object');
        return 'It casts '.$expr.' as a '.$casts[$node->flags];
    }

    function read_ast_static_call($node) {
        $class = $this->renderIt($node->children['class']);
        $method = $this->renderIt($node->children['method'], self::SILENT);
        $arguments = $this->renderIt($node->children['args']);

        return 'a static call to the method '.$method.' of the class '.$class.', '.$arguments;
    }

    function read_ast_post_inc($node) { 
        $var = $this->renderIt($node->children['var']);

        return 'the post-incrementation of '.$var;
    }

    function read_ast_post_dec($node) { 
        $var = $this->renderIt($node->children['var']);

        return 'the post-decrementation of '.$var;
    }
    
    function read_ast_namespace($node) { 
        $namespace = $this->renderIt($node->children['name']);

        return 'the namespace '.$namespace;
    }

    function read_ast_use($node) { 
        $namespace = $this->renderIt($node->children[0]->children['name']);
        if (empty($node->children[0]->children['alias'])) {
            $alias = basename($namespace);
        } else {
            $alias = $this->renderIt($node->children[0]->children['alias']);
        }

        return 'the aliasing of the namespace '.$namespace.' with the alias '.$alias;
    }
    
    function read_ast_throw($node) { 
        $expr = $this->renderIt($node->children['expr']);
        return 'the throw of '.$expr;
    }

    function read_ast_static_prop($node) { 
        $c = count($node->children);
        $return = $this->integer2English($c).' static propert'.($c > 1 ? 'ies' : 'y').' : ';
        
        $propertyList = $this->renderList($node->children);
        
        $return .= $propertyList;

        return $return;
    }
    
    function read_ast_try($node) { 
        $try = $this->renderIt($node->children['try']);
        $catches = $this->renderList($node->children['catches']->children);

        return 'the attempt to run '.$try.' while monitoring '.$catches;
    }
    
    function read_ast_catch($node) { 
        $class = $this->renderIt($node->children['class']);
        $var = $this->renderIt($node->children['var']);
        $stmts = $this->renderIt($node->children['stmts']);

        return 'the catch of the exception '.$class.' in the variable '.$var.' with the following : '.$stmts;
    }
    
    function read_ast_continue($node) {
        $depth = (int) $node->children['depth'];
        
        if ($depth == 0) {
            $what = 'current';
        } elseif ($depth == 1) {
            $what = 'current';
        } elseif ($depth == 2) {
            $what = 'above';
        } elseif ($depth == 3) {
            $what = 'above-above';
        } else {
            $what = $this->integer2English($deep - 1).' level higher';
        }

        return 'the reset of the '.$what.' loop';
    }
    
    function read_ast_unset($node) { 
        $var = $this->renderIt($node->children['var']);

        return 'the destruction of '.$var;
    }

    function read_ast_do_while($node) {
        $stmts = $this->renderIt($node->children['stmts']);
        $cond = $this->renderIt($node->children['cond']);
        
        return 'the loop of '.$stmts.' while the condition of '.$cond;
    }

    function read_ast_while($node) { 
        $stmts = $this->renderIt($node->children['stmts']);
        $cond = $this->renderIt($node->children['cond']);
        
        return 'the loop of '.$stmts.' as long as the condition of '.$cond;
    }

    function read_ast_magic_const($node) { 
        $magic = array(
ast\flags\MAGIC_LINE   => '__LINE__',
ast\flags\MAGIC_FILE   => '__FILE__',
ast\flags\MAGIC_DIR   => '__DIR__',
ast\flags\MAGIC_NAMESPACE   => '__NAMESPACE__',
ast\flags\MAGIC_FUNCTION   => '__FUNCTION__',
ast\flags\MAGIC_METHOD   => '__METHOD__',
ast\flags\MAGIC_CLASS   => '__CLASS__',
ast\flags\MAGIC_TRAIT   => '__TRAIT__',
        );
        $name = $magic[$node->flags];
        
        return 'the magic constant called '.$name;
    }
    
    function read_ast_for($node) { 
        $init = $this->renderIt($node->children['init']);
        $cond = $this->renderIt($node->children['cond']);
        $loop = $this->renderIt($node->children['loop']);
        $stmts = $this->renderIt($node->children['stmts']);

        return 'the for loop, initialized with '.$init.
                             'continued with '.$cond.
                             'incremented by '.$cond.
                             'running the following : '.$stmts;
    }

    function read_ast_expr_list($node) { 
        return $this->renderList($node->children);
    }

    function read_ast_switch($node) { 
        $cond = $this->renderIt($node->children['cond']);
        
        $stmts = $this->renderList($node->children['stmts']->children);

        return 'the switch on '.$cond.' and the cases '.$stmts;
    }
    
    function read_ast_switch_case($node) { 
        $stmts = $this->renderIt($node->children['stmts']);

        if ($node->children['cond'] === null) {
            // default 
            return 'when it is any other case, it does '.$stmts;
        } else {
            // a case
            $cond = $this->renderIt($node->children['cond']);
    
            return 'when it compares with '.$cond.', it does '.$stmts;
        }
    }
    
    function read_ast_break($node) { 
        $depth = (int) $node->children['depth'];
        
        if ($depth == 0) {
            $what = 'current';
        } elseif ($depth == 1) {
            $what = 'current';
        } elseif ($depth == 2) {
            $what = 'above';
        } elseif ($depth == 3) {
            $what = 'above-above';
        } else {
            $what = $this->integer2English($deep - 1).' level higher';
        }

        return 'the interruption of the '.$what.' level of execution';
    }
    
    function read_ast_echo($node) { 
        $expr = $this->renderIt($node->children['expr']);

        return 'the display on stdout of '.$expr;
    }

    function read_ast_include_or_eval($node) { 
        $expr = $this->renderIt($node->children['expr']);

        return 'the inclusion of the file '.$expr;
    }

    function read_ast_closure_uses($node) { 
        return $this->renderList($node->children);
    }
    
    function read_ast_closure_var($node) { 
        return '$'.$node->children['name'];
    }

    function read_ast_global($node) { 
        $var = $this->renderIt($node->children['var']);

        return 'the declaration of a global variable '.$var;
    }

    function read_ast_ref($node) { 
        $var = $this->renderIt($node->children['var']);

        return 'the referece on '.$var;
    }    

    function read_ast_assign_ref($node) { 
        $var = $this->renderIt($node->children['var']);
        $expression = $this->renderIt($node->children['expr']);
        
        return 'the assignation by reference of '.$expression.' to '.$var;
    }
    
    //////////////////////////////////////////////////////////////////////////////////////////
    ////////Defintions for the PHP native functions
    //////////////////////////////////////////////////////////////////////////////////////////
    function function_file_exists($args) {
        $file = $this->renderIt($args->children[0], self::SILENT);
        return 'the verification of the existence of the file '.$file;
    }

    function function_unlink($args) {
        $file = $this->renderIt($args->children[0], self::SILENT);
        return 'the destruction of the file '.$file;
    }

    function function_file_get_contents($args) {
        $file = $this->renderIt($args->children[0], self::SILENT);
        return 'the content of the file '.$file;
    }

    function function_print_r($args) {
        $expr = $this->renderIt($args->children[0], self::SILENT);
        if(isset($args->children[1])) {
            $what = 'display or return';
        } else {
            $what = 'display';
        }
        return 'the '.$what.' of human-readable information about '.$expr;
    }

    function function_str_replace($args) {
        $origin = $this->renderIt($args->children[0], self::SILENT);
        $destination = $this->renderIt($args->children[1], self::SILENT);
        $haystack = $this->renderIt($args->children[2], self::SILENT);
        return 'the replacement of '.$origin.' by '.$destination.' in '.$haystack;
    }
    
    function function_array_map($args) {
        $callback = $this->renderIt($args->children[0], self::SILENT);
        $array = $this->renderIt($args->children[1], self::SILENT);

        assert(!isset($args->children[2]), 'missing array_map 2 args+');

        return 'the application of '.$callback.' to every element of the array '.$array;
    }

    function function_strtolower($args) {
        $string = $this->renderIt($args->children[0], self::SILENT);
        return 'the conversion to lowercase of '.$string;
    }

    function function_is_integer($args) {
        $var = $this->renderIt($args->children[0], self::SILENT);
        return 'the verification that '.$var.' is an integer';
    }

    function function_is_string($args) {
        $var = $this->renderIt($args->children[0], self::SILENT);
        return 'the verification that '.$var.' is a string';
    }

    function function_gettype($args) {
        $var = $this->renderIt($args->children[0], self::SILENT);
        return 'the reading of the type of '.$var;
    }

    function function_assert($args) {
        $assert = $this->renderIt($args->children[0], self::SILENT);
        if (isset($args->children[1])) {    
            $message = $this->renderIt($args->children[1], self::SILENT);
        }
        return 'the assertion of '.$assert.(isset($message) ? 'then displays the message '.$message: '');
    }

    function function_method_exists($args) {
        $class = $this->renderIt($args->children[0], self::SILENT);
        $method = $this->renderIt($args->children[1], self::SILENT);
        return 'the verification that the method '.$method.' exists in the class '.$class;
    }

    function function_get_defined_constants($args) {
        return 'the listing of all defined constants in the current PHP binary ';
    }

    function function_array_filter($args) {
        $array = $this->renderIt($args->children[0], self::SILENT);
        if (isset($args->children[1])) {
            $closure = ' with '.$this->renderIt($args->children[1], self::SILENT);
        } else {
            $closure = ' with the default filter that removes false values';
        }

        return 'the filtering of the array '.$array.$closure;
    }

    function function_strpos($args) {
        $haystack = $this->renderIt($args->children[0], self::SILENT);
        $needle = $this->renderIt($args->children[1], self::SILENT);
        $offset = isset($args->children[2]) ? $this->renderIt($args->children[2], self::SILENT) : 0;
        return 'the research of the position of the first occurrence of '.$needle.' in the '.$haystack.', starting at the position of '.$offset;
    }

    function function_array_flip($args) {
        $array = $this->renderIt($args->children[0], self::SILENT);
        return 'the exchange of all the keys with their associated values in the array '.$array;
    }

    function function_is_dir($args) {
        $dir = $this->renderIt($args->children[0], self::SILENT);

        return 'the verification that '.$dir.' is a directory';
    }

    function function_is_file($args) {
        $dir = $this->renderIt($args->children[0], self::SILENT);

        return 'the verification that '.$dir.' is a file';
    }

    function function_substr($args) {
        $string = $this->renderIt($args->children[0], self::SILENT);
        $start = $this->renderIt($args->children[1], self::SILENT);
        if (isset($args->children[2])) {  
            $end = $this->renderIt($args->children[2], self::SILENT);
        } else {
            $end = 'the end';
        }
        
        return 'the reduction of the string '.$string.', starting at '.$start.' and ending at '.$end;
    }

    function function_header($args) {
        $headers = $this->renderIt($args->children[0], self::SILENT);
        if (isset($args->children[1])) {
            $code = $this->renderIt($args->children[1], self::SILENT);
            $replace = ', replacing an identical and previously set HTTP header based on '.$code;
        } else {
            $replace = ', without replacing an identical and previously set HTTP header';
        }
        if (isset($args->children[2])) {
            $code = $this->renderIt($args->children[2], self::SILENT);
            $httpcode = ', changing the HTTP code to '.$code;
        } else {
            $httpcode = 'without changing the HTTP code';
        }

        return 'the sending as raw HTTP header of '.$headers.$replace.$httpcode;
    }

    function function_trim($args) {
        $var = $this->renderIt($args->children[0], self::SILENT);
        if (isset($args->children[1])) {
            $chars = $this->renderIt($args->children[1], self::SILENT);
        } else {
            $chars = 'tabulation, new line, line feed, null-byte and vertical tab';
        }

        return 'the stripping of the characters '.$chars.' from the beginning and the end of '.$var;
    }

    function function_implode($args) {
        $array = $this->renderIt($args->children[0], self::SILENT);
        if (isset($args->children[1])) {
            $glue = $this->renderIt($args->children[1], self::SILENT);
        } else {
            $glue = 'empty string';
        }
        return 'the join of all the elements in '.$array.' with the glue '.$glue;
    }

    function function_strip_tags($args) {
        $var = $this->renderIt($args->children[0], self::SILENT);
        if (isset($args->children[1])) {
            $allowing = 'allowing only '.$this->renderIt($args->children[1], self::SILENT);
        } else {
            $allowing = 'allowing absolutely no tags';
        }
        return 'the strip of HTML and PHP tags from '.$var.' '.$allowing;
    }

    function function_file_put_contents($args) {
        $file = $this->renderIt($args->children[0], self::SILENT);
        $data = $this->renderIt($args->children[1], self::SILENT);
        if (isset($args->children[2])) {
            $flags = 'with the option of '.$this->renderIt($args->children[2], self::SILENT);
        } else {
            $flags = 'without any special option';
        }
        if (isset($args->children[3])) {
            $context = 'in the contexted file-system '.$this->renderIt($args->children[3], self::SILENT);
        } else {
            $context = 'in the local file system';
        }

        return 'the write the content of '.$data.' in the file '.$file.' '.$flags.' '.$context;
    }
}

?>