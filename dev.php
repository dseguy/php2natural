<?php

class Sentence {
    private $subject;
    private $verb;
    private $complement;
    private $indirectComplement;
    
    public function __construct() {
    
    }
    
    public function addSubject($subject) {
        $this->subject = $subject;
    }

    public function addVerb($verb) {
        $this->verb = $verb;
    }

    public function addComplement($complement) {
        $this->complement = $complement;
    }

    public function addIndirectComplement($preposition, $complement) {
        $this->indirectComplements[(string) $preposition] = $complement;
    }
    
    public function __toString() {
        list($preposition, $complement) = each($this->indirectComplements);
        $indirectComplement = $preposition.' '.$complement;
        $this->verb->setOption($this->verb::SUBJECT, $this->verb::IT);
        
        
        return ucfirst($this->subject).' '.$this->verb.' '.$this->complement.' '.$indirectComplement.'.';
    }
}

class Word {
    const UNKNOWN = 0;
    const WORD = 1;
    const VERBE = 2;
    const ADJECTIVE = 3;
    const NUMERAL= 4;
    const PREPOSITION = 5;
    
    const NUMBER = 'number';
    const PLURAL = 'plural';
    const SINGLE = 'single';
    
    protected $options = array(self::NUMBER => self::UNKNOWN);
    protected $word = null;
    
    public function __construct($word) {
        $this->word = strtolower((string) $word);
    }
    
    public function setOption($type, $value) {
        $this->options[$type] = $value;
    }

    public function getOption($type) {
        return $this->options[$type];
    }
    
    public function __toString() {
        return $this->word;
    }
}

class Noun extends Word {
    public function __toString() {
        return $this->word.($this->getOption[self::NUMBER] === self::PLURAL ? 's' : '');
    }
}

class Adjective extends Word {
    public function __toString() {
        return (string) $this->word;
    }
}

class Preposition extends Word { }

class Pronoun extends Word { }

class Verb extends Word {
    const I    = 1;
    const YOU  = 2;
    const HE   = 3;
    const SHE  = 4;
    const IT   = 5;
    const WE   = 6;
    const THEY = 7;
    const INFINITIVE = 8;
    
    const SUBJECT = 9;
    
    public function __construct($word) {
        parent::__construct($word);
        
        $this->options[self::SUBJECT] = self::INFINITIVE;
    }

    public function __toString() {
        if ($this->options[self::SUBJECT] === self::INFINITIVE) {
            return 'to '.$this->word;
        } elseif ($this->options[self::SUBJECT] === self::IT ||
                  $this->options[self::SUBJECT] === self::HE ||
                  $this->options[self::SUBJECT] === self::SHE ) {
            return $this->word.'s';
        } else {
            return $this->word.'s';
        }
    }
}
