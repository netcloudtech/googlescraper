<?php
class GoogleScraper {
    private $proxy;
    private $results = [];
    private $retryCount = 3;
    private $retryDelay = 60;
    private $lastRequestTime = 0;
    private $minRequestDelay = 5;
    private $failCount = 0;
    
    public function __construct($proxyString) {
        if (is_array($proxyString)) {
            $this->proxy = [
                'host' => $proxyString['host'] ?? '',
                'port' => $proxyString['port'] ?? '',
                'username' => $proxyString['username'] ?? '',
                'password' => $proxyString['password'] ?? ''
            ];
        } else {
            list($host, $port, $username, $password) = explode(':', $proxyString);
            $this->proxy = [
                'host' => $host,
                'port' => $port,
                'username' => $username,
                'password' => $password
            ];
        }
    }

    private function extractWebsite($xpath, $card) {
        $websiteQueries = [
            './/div[contains(@class, "VkpGBb")]//a[contains(@href, "http")]/@href',
            './/a[contains(@aria-label, "Web Sitesi")]/@href',
            './/a[contains(@data-ved, "") and contains(@href, "http")]/@href',
            './/div[contains(@class, "VkpGBb")]//a[@data-website]/@data-website',
            './/a[contains(@href, "http") and not(contains(@href, "google"))]/@href'
        ];
        
        foreach ($websiteQueries as $query) {
            try {
                $nodes = $xpath->query($query, $card);
                if ($nodes->length > 0) {
                    foreach ($nodes as $node) {
                        $url = '';
                        if ($node->nodeValue) {
                            $url = trim($node->nodeValue);
                        } 
                        else {
                            foreach (['href', 'data-action-url', 'data-website'] as $attr) {
                                if ($node->hasAttribute($attr)) {
                                    $url = trim($node->getAttribute($attr));
                                    break;
                                }
                            }
                        }

                        if (!empty($url)) {
                            $url = $this->cleanUrl($url);
                            if (!empty($url)) {
                                error_log("Website URL bulundu: " . $url);
                                return $url;
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Website XPath sorgusu başarısız: " . $query . " - " . $e->getMessage());
                continue;
            }
        }
        
        try {
            $onClickNodes = $xpath->query('.//div[contains(@class, "pJ3Ci")]//a[@onclick]/@onclick', $card);
            if ($onClickNodes->length > 0) {
                foreach ($onClickNodes as $node) {
                    $onClickText = $node->nodeValue;
                    if (preg_match('/window\.open\(["\']([^"\']+)["\']/i', $onClickText, $matches)) {
                        $url = $this->cleanUrl($matches[1]);
                        if (!empty($url)) {
                            error_log("OnClick'ten website URL bulundu: " . $url);
                            return $url;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log("OnClick XPath sorgusu başarısız: " . $e->getMessage());
        }
        
        try {
            $vedNodes = $xpath->query('.//a[@data-ved]/@href', $card);
            if ($vedNodes->length > 0) {
                foreach ($vedNodes as $node) {
                    if ($node->nodeValue && !strpos($node->nodeValue, 'maps.google')) {
                        $url = $this->cleanUrl($node->nodeValue);
                        if (!empty($url)) {
                            error_log("Data-ved'den website URL bulundu: " . $url);
                            return $url;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Data-ved XPath sorgusu başarısız: " . $e->getMessage());
        }

        return '';
    }

    private function cleanUrl($url) {
        try {
            $url = trim($url);
            $url = urldecode($url);
            
            if (preg_match('/url\?.*?(?:url|q)=([^&]+)/i', $url, $matches)) {
                $url = urldecode($matches[1]);
            }
            
            $url = preg_replace('/^javascript:.*?\([\'"](.+?)[\'"]\).*$/i', '$1', $url);
            
            if (!empty($url) && !preg_match('~^(?:f|ht)tps?://~i', $url)) {
                $url = 'https://' . ltrim($url, '/');
            }
            
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                if (!preg_match('/(google\.|goo\.gl|maps\.)/i', $url)) {
                    return $url;
                }
            }
        } catch (Exception $e) {
            error_log("URL temizleme hatası: " . $e->getMessage());
        }
        
        return '';
    }

    private function cleanOpeningYearText($text) {
        $patterns = [
            '/\d+ yıldan daha uzun süre önce açıldı/i',
            '/\d+ yıldan daha uzun/i',
            '/\d+ yıldan fazla/i',
            '/uzun süredir/i',
            '/yeni açıldı/i',
            '/\d+ yıl önce/i',
            '/açıldı/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $openingInfo = trim($matches[0]);
                $cleanedText = trim(preg_replace('/' . preg_quote($openingInfo, '/') . '\s*[·•]\s*/', '', $text));
                $cleanedText = trim(str_replace($openingInfo, '', $cleanedText));
                if (preg_match('/^[\s\.\,\-·•]*$/', $cleanedText)) {
                    return '';
                }
                
                return $cleanedText;
            }
        }
        
        return $text;
    }

    private function extractPhoneNumbers($text) {
        $patterns = [
            '/\+\s*90\s*[0-9]{3}\s*[0-9]{3}\s*[0-9]{2}\s*[0-9]{2}/',
            '/\+\s*90\s*\(\s*[0-9]{3}\s*\)\s*[0-9]{3}\s*[0-9]{2}\s*[0-9]{2}/',
            
            '/0\s*[0-9]{3}\s*[0-9]{3}\s*[0-9]{2}\s*[0-9]{2}/',
            '/0\s*[0-9]{3}\s*[0-9]{3}\s*[0-9]{4}/',

            '/\(\s*0\s*[0-9]{3}\s*\)\s*[0-9]{3}\s*[0-9]{2}\s*[0-9]{2}/',
            '/\(\s*0\s*[0-9]{3}\s*\)\s*[0-9]{7}/',
            
            '/\(\s*[0-9]{3,4}\s*\)\s*[0-9]{3}\s*[0-9]{2}\s*[0-9]{2}/',
            '/[0-9]{4}\s*[0-9]{3}\s*[0-9]{2}\s*[0-9]{2}/',
            
            '/0\s*[0-9]{3,4}\s*[0-9]{3}\s*[0-9]{2}\s*[0-9]{2}/',
            '/[0-9]{3,4}\s*[0-9]{3}\s*[0-9]{2}\s*[0-9]{2}/',
            
            '/05[0-9]{2}\s*[0-9]{3}\s*[0-9]{2}\s*[0-9]{2}/',
            '/5[0-9]{2}\s*[0-9]{3}\s*[0-9]{2}\s*[0-9]{2}/'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return trim($matches[0]);
            }
        }
        
        return '';
    }
    private function isOpeningHours($text) {
        $openingHoursPatterns = [
            '/açılış/i',
            '/kapalı/i',
            '/açık/i',
            '/kapanış/i',
            '/open/i',
            '/closed/i',
            '/24 saat/i',
            '/haftanın/i',
            '/\b\d{1,2}:\d{2}\b/',
            '/pazartesi|salı|çarşamba|perşembe|cuma|cumartesi|pazar/i',
            '/çalışma saatleri/i',
            '/mesai/i'
        ];

        foreach ($openingHoursPatterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }
        
        return false;
    }

    private function isAddress($text) {
        if (preg_match('/\d+ yıldan daha uzun süre önce açıldı/i', $text) || 
            preg_match('/\d+ yıldan daha uzun/i', $text) ||
            preg_match('/yeni açıldı/i', $text)) {
            return false;
        }
        
        $addressPatterns = [
            '/mahalle/i',
            '/cadde/i',
            '/sokak/i',
            '/bulvar/i',
            '/no:/i',
            '/apt/i',
            '/kat/i',
            '/daire/i',
            '/mah\./i',
            '/cad\./i',
            '/sok\./i',
            '/blv\./i',
            '/blok/i',
            '/site/i',
            '/bina/i',
            '/plaza/i',
            '/merkez/i',
            '/köy/i',
            '/köyü/i',
            '/ilçesi/i',
            '/merkezi/i',
            '/kompleks/i',
            '/apartman/i',
            '/İstanbul|Ankara|İzmir|Antalya|Bursa|Adana|Konya|Gaziantep|Şanlıurfa|Kocaeli|Mersin|Diyarbakır|Hatay|Manisa|Kayseri/i',
            '/adana|adıyaman|afyon|ağrı|amasya|ankara|antalya|artvin|aydın|balıkesir|bilecik|bingöl|bitlis|bolu|burdur|bursa|çanakkale|çankırı|çorum|denizli|diyarbakır|edirne|elazığ|erzincan|erzurum|eskişehir|gaziantep|giresun|gümüşhane|hakkari|hatay|ısparta|mersin|istanbul|izmir|kars|kastamonu|kayseri|kırklareli|kırşehir|kocaeli|konya|kütahya|malatya|manisa|kahramanmaraş|mardin|muğla|muş|nevşehir|niğde|ordu|rize|sakarya|samsun|siirt|sinop|sivas|tekirdağ|tokat|trabzon|tunceli|şanlıurfa|uşak|van|yozgat|zonguldak|aksaray|bayburt|karaman|kırıkkale|batman|şırnak|bartın|ardahan|ığdır|yalova|karabük|kilis|osmaniye|düzce/i',
            '/\b[0-9]{5}\b/', 
            '/d:[0-9]+/i',   
            '/no:[0-9]+/i', 
            '/[0-9]+\. sokak/i',
            '/[a-z]+\/[a-z]+/i',
            '/sk\.|cd\.|mh\./i'
        ];

        foreach ($addressPatterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }
        
        return false;
    }
    private function extractDetailsFromCard($xpath, $card) {
        $details = [
            'address' => '',
            'phone' => '',
            'opening_hours' => ''
        ];
        
        $phoneQueries = [
            './/span[contains(@aria-label, "telefon")]',
            './/span[contains(@class, "phone")]',
            './/a[contains(@href, "tel:")]',
            './/div[contains(text(), "+90") or contains(text(), "0850") or contains(text(), "0212") or contains(text(), "0216") or contains(text(), "0224") or contains(text(), "0312") or contains(text(), "0232") or contains(text(), "0242") or contains(text(), "0322") or contains(text(), "0332") or contains(text(), "0352") or contains(text(), "0362") or contains(text(), "0412") or contains(text(), "0422") or contains(text(), "0432") or contains(text(), "0442") or contains(text(), "0452") or contains(text(), "0462") or contains(text(), "0472") or contains(text(), "0482") or contains(text(), "0535") or contains(text(), "0505") or contains(text(), "0532") or contains(text(), "0533") or contains(text(), "0536") or contains(text(), "0537") or contains(text(), "0538") or contains(text(), "0539") or contains(text(), "0541") or contains(text(), "0542") or contains(text(), "0543") or contains(text(), "0544") or contains(text(), "0545") or contains(text(), "0546") or contains(text(), "0547") or contains(text(), "0548") or contains(text(), "0549") or contains(text(), "0551") or contains(text(), "0552") or contains(text(), "0553") or contains(text(), "0554") or contains(text(), "0555") or contains(text(), "0556") or contains(text(), "0557") or contains(text(), "0558") or contains(text(), "0559")]'
        ];

        foreach ($phoneQueries as $query) {
            try {
                $nodes = $xpath->query($query, $card);
                if ($nodes && $nodes->length > 0) {
                    foreach ($nodes as $node) {
                        $text = trim($node->nodeValue);
                        if (empty($text)) continue;
                        
                        $phone = $this->extractPhoneNumbers($text);
                        if (!empty($phone)) {
                            $details['phone'] = $phone;
                            break 2;
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Telefon XPath sorgusu başarısız: " . $query . " - " . $e->getMessage());
                continue;
            }
        }
        
        $textContainers = [
            './/div[contains(@class, "rllt__details")]//div',
            './/div[contains(@class, "rllt__wrapped-text")]',
            './/div[contains(@class, "rllt__details")]//span',
            './/span[contains(@class, "OSrXXb")]',
            './/div[contains(@class, "b8rQB")]',
            './/div[@data-dtype="d3adr"]',
            './/div[@data-dtype="d3ph"]',
            './/div[contains(@class, "uxVfU")]'
        ];
        
        $addressCandidates = [];
        $openingHoursCandidates = [];
        $yearInfo = '';
        
        foreach ($textContainers as $query) {
            try {
                $nodes = $xpath->query($query, $card);
                if ($nodes && $nodes->length > 0) {
                    foreach ($nodes as $node) {
                        $text = trim($node->nodeValue);
                        if (empty($text)) continue;
                        
                        if (preg_match('/\d+ yıldan daha uzun süre önce açıldı/i', $text) || 
                            preg_match('/\d+ yıldan daha uzun/i', $text) ||
                            preg_match('/yeni açıldı/i', $text)) {
                            $yearInfo = $text;
                            
                            $actualAddress = $this->cleanOpeningYearText($text);
                            if (!empty($actualAddress) && $this->isAddress($actualAddress)) {
                                $addressCandidates[] = $actualAddress;
                            }
                            
                            continue;
                        }
                        
                        if ($this->isOpeningHours($text)) {
                            $openingHoursCandidates[] = $text;
                            continue;
                        }
                        
                        if (preg_match('/\+?[0-9()\s-]{9,20}/', $text)) {
                            continue;
                        }
                        
                        if ($this->isAddress($text)) {
                            $addressCandidates[] = $text;
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Metin XPath sorgusu başarısız: " . $query . " - " . $e->getMessage());
                continue;
            }
        }
        
        if (!empty($addressCandidates)) {
            usort($addressCandidates, function($a, $b) {
                return strlen($b) - strlen($a);
            });
            
            $details['address'] = $addressCandidates[0];
        }
        
        if (!empty($openingHoursCandidates)) {
            $details['opening_hours'] = $openingHoursCandidates[0];
        } elseif (!empty($yearInfo)) {
            $details['opening_hours'] = $yearInfo;
        }

        if (empty($details['address']) || empty($details['opening_hours'])) {
            try {
                $allText = $xpath->query('.//text()', $card);
                if ($allText && $allText->length > 0) {
                    foreach ($allText as $textNode) {
                        $text = trim($textNode->nodeValue);
                        if (empty($text) || strlen($text) < 5) continue;
                        
                        if (empty($details['phone'])) {
                            $phone = $this->extractPhoneNumbers($text);
                            if (!empty($phone)) {
                                $details['phone'] = $phone;
                            }
                        }
                        
                        if (empty($details['address']) && $this->isAddress($text) && !$this->isOpeningHours($text)) {
                            $details['address'] = $text;
                        }
                        
                        if (empty($details['opening_hours']) && $this->isOpeningHours($text)) {
                            $details['opening_hours'] = $text;
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Tüm metin çıkarma başarısız: " . $e->getMessage());
            }
        }
        
        return $details;
    }

    public function searchBusinesses($keyword, $location, $pages = 1) {
        $searchQuery = urlencode("$keyword $location");
        $this->failCount = 0;
        
        for($page = 0; $page < $pages; $page++) {
            if($this->failCount >= 5) {
                error_log("Çok fazla başarısız istek, arama sonlandırılıyor.");
                break;
            }

            $url = "https://www.google.com/search?q={$searchQuery}&tbm=lcl" . ($page > 0 ? "&start=" . ($page * 20) : "");
            
            $html = $this->makeRequestWithRetry($url);
            if(!$html) {
                $this->failCount++;
                error_log("Sayfa {$page} için veri alınamadı, devam ediliyor... (Başarısız istek sayısı: {$this->failCount})");
                continue;
            }
            
            $previousValue = libxml_use_internal_errors(true);
            $dom = new DOMDocument('1.0', 'UTF-8');
            $html = '<?xml encoding="UTF-8">' . $html;
            @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
            $dom->encoding = 'UTF-8';
            $xpath = new DOMXPath($dom);
            libxml_use_internal_errors($previousValue);
            
            $cards = $xpath->query('//div[contains(@class, "VkpGBb")]');
            
            if($cards->length === 0) {
                $this->failCount++;
                error_log("Sayfa {$page} için işletme kartı bulunamadı (Başarısız istek sayısı: {$this->failCount})");
                continue;
            }
            
            $this->failCount = 0;
            
            foreach($cards as $card) {
                try {
                    $name = $this->getNodeValue($xpath, './/div[contains(@class, "dbg0pd")]', $card);
                    $details = $this->extractDetailsFromCard($xpath, $card);
                    $rating = '';
                    $ratingNodes = $xpath->query('.//span[contains(@class, "yi40Hd")]', $card);
                    if ($ratingNodes->length > 0) {
                        $rating = trim($ratingNodes->item(0)->nodeValue);
                    }
                    if (empty($rating)) {
                        $ratingNodes = $xpath->query('.//span[contains(@aria-label, "yıldız")]', $card);
                        if ($ratingNodes->length > 0) {
                            $rating = trim($ratingNodes->item(0)->nodeValue);
                        }
                    }

                    $reviews = '';
                    $reviewNodes = $xpath->query('.//span[contains(@class, "RDApEe")]', $card);
                    if ($reviewNodes->length > 0) {
                        $reviews = trim($reviewNodes->item(0)->nodeValue);
                        if (preg_match('/\((\d+)\)/', $reviews, $matches)) {
                            $reviews = $matches[1];
                        }
                    }
                    
                    $website = $this->extractWebsite($xpath, $card);

                    $business = [
                        'name' => $name,
                        'address' => $details['address'],
                        'phone' => $details['phone'],
                        'opening_hours' => $details['opening_hours'],
                        'website' => $website,
                        'rating' => $rating,
                        'reviews' => $reviews
                    ];
                    
                    if(!empty($business['name'])) {
                        $this->results[] = $business;
                    }
                    
                } catch (Exception $e) {
                    error_log("İşletme verisi çıkarılırken hata: " . $e->getMessage());
                    continue;
                }
            }
            
            $this->enforceRateLimit();
            sleep(rand(2, 5));
        }
        
        return $this->results;
    }
    
    private function makeRequestWithRetry($url) {
        $attempts = 0;
        while($attempts < $this->retryCount) {
            $response = $this->makeRequest($url);
            if($response !== false) {
                return $response;
            }
            $attempts++;
            if($attempts < $this->retryCount) {
                $waitTime = $this->retryDelay * $attempts;
                error_log("Deneme {$attempts} başarısız, {$waitTime} saniye bekleniyor...");
                sleep($waitTime);
            }
        }
       return false;
    }
    
    private function makeRequest($url) {
        $this->enforceRateLimit();
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_PROXY => $this->proxy['host'],
            CURLOPT_PROXYPORT => $this->proxy['port'],
            CURLOPT_PROXYUSERPWD => $this->proxy['username'] . ':' . $this->proxy['password'],
            CURLOPT_PROXYTYPE => CURLPROXY_HTTP,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => $this->getRandomUserAgent(),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_ENCODING => 'gzip, deflate',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language: tr-TR,tr;q=0.9,en-US;q=0.8,en;q=0.7',
                'Cache-Control: no-cache',
                'Pragma: no-cache',
                'Connection: keep-alive'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        $this->lastRequestTime = time();
        
        if($error) {
            error_log("CURL hatası: " . $error);
            return false;
        }
        
        if($httpCode === 429) {
            error_log("Rate limit aşıldı (HTTP 429) - Daha uzun bekleme süresi uygulanacak");
            sleep(rand(60, 120));
            return false;
        }
        
        if($httpCode !== 200) {
            error_log("HTTP hatası: " . $httpCode);
            return false;
        }
        
        return $response;
    }
    
    private function enforceRateLimit() {
        if($this->lastRequestTime > 0) {
            $timeSinceLastRequest = time() - $this->lastRequestTime;
            if($timeSinceLastRequest < $this->minRequestDelay) {
                $sleepTime = $this->minRequestDelay - $timeSinceLastRequest + rand(1, 3);
                sleep($sleepTime);
            }
        }
    }    
    
    private function getNodeValue($xpath, $query, $context = null) {
        try {
            $node = $xpath->query($query, $context)->item(0);
            return $node ? trim($node->nodeValue) : '';
        } catch (Exception $e) {
            error_log("XPath sorgusu başarısız: " . $query);
            return '';
        }
    }
    
    private function getAttribute($xpath, $query, $attribute, $context = null) {
        try {
            $node = $xpath->query($query, $context)->item(0);
            return $node ? $node->getAttribute($attribute) : '';
        } catch (Exception $e) {
            error_log("XPath attribute sorgusu başarısız: " . $query);
            return '';
        }
    }
    
private function getRandomUserAgent() {
        $agents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.5 Safari/605.1.15',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36 Edg/119.0.0.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/119.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 16_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.5 Mobile/15E148 Safari/604.1'
        ];
        return $agents[array_rand($agents)];
    }
    
    public function saveToJson($filename) {
        if(empty($this->results)) {
            error_log("Kaydedilecek sonuç bulunamadı");
            return false;
        }
        
        $success = file_put_contents($filename, json_encode($this->results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $success !== false;
    }
    
public function saveToCSV($filename) {
    if(empty($this->results)) {
        error_log("Kaydedilecek sonuç bulunamadı");
        return false;
    }
    $this->cleanResults();
    $fp = fopen($filename, 'w');
    if($fp === false) {
        error_log("CSV dosyası oluşturulamadı");
        return false;
    }
    fwrite($fp, "\xEF\xBB\xBF");
    
    $headers = [
        'name' => 'Firma İsmi',
        'address' => 'Firma Adresi',
        'phone' => 'İletişim Numarası',
        'opening_hours' => 'Açılış/Kapanış Saatleri',
        'website' => 'Website Adresi',
        'rating' => 'Google Derecelendirmesi',
        'reviews' => 'Kullanıcı Yorumları/Değerlendirmeleri'
    ];
    
    fputcsv($fp, array_values($headers));
    
    foreach($this->results as $business) {
        if (!empty($business['opening_hours'])) {
            $phone = $this->extractPhoneNumbers($business['opening_hours']);
            if (!empty($phone)) {
                $business['opening_hours'] = trim(str_replace($phone, '', $business['opening_hours']));
                if (empty($business['phone'])) {
                    $business['phone'] = $phone;
                }
            }
        }
        
        $row = [];
        foreach(array_keys($headers) as $key) {
            $value = $business[$key] ?? '';
            $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            
            if ($key === 'opening_hours') {
                if (preg_match('/(\d+) yıldan daha uzun süre önce açıldı/i', $value)) {
                    $value = preg_replace('/(·|\s)+/', ' ', $value);
                }
            }
            
            $row[] = $value;
        }
        fputcsv($fp, $row);
    }
    
    fclose($fp);
    return true;
}
public function saveToExcelCSV($filename) {
    if(empty($this->results)) {
        error_log("Kaydedilecek sonuç bulunamadı");
        return false;
    }
    $this->cleanResults();
    
    $fp = fopen($filename, 'w');
    if($fp === false) {
        error_log("CSV dosyası oluşturulamadı");
        return false;
    }
    fwrite($fp, "\xEF\xBB\xBF");
    
    $headers = [
        'name' => 'Firma İsmi',
        'address' => 'Firma Adresi',
        'phone' => 'İletişim Numarası',
        'opening_hours' => 'Açılış/Kapanış Saatleri',
        'website' => 'Website Adresi',
        'rating' => 'Google Derecelendirmesi',
        'reviews' => 'Kullanıcı Yorumları/Değerlendirmeleri'
    ];
    
    fwrite($fp, implode("\t", array_values($headers)) . "\r\n");
    
    foreach($this->results as $business) {
        if (!empty($business['opening_hours'])) {
            $phone = $this->extractPhoneNumbers($business['opening_hours']);
            if (!empty($phone)) {
                $business['opening_hours'] = trim(str_replace($phone, '', $business['opening_hours']));
                if (empty($business['phone'])) {
                    $business['phone'] = $phone;
                }
            }
        }
        
        $row = [];
        foreach(array_keys($headers) as $key) {
            $value = $business[$key] ?? '';
            $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $value = str_replace('"', '""', $value);
            $row[] = '"' . $value . '"';
        }
        
        fwrite($fp, implode("\t", $row) . "\r\n");
    }
    
    fclose($fp);
    return true;
}
    
    public function cleanResults() {
    foreach ($this->results as $key => $business) {
        if (!empty($business['address'])) {
            $cleanedAddress = $this->cleanOpeningYearText($business['address']);
            if (empty($cleanedAddress) || strlen($cleanedAddress) < 5) {
                $this->results[$key]['address'] = '';
            } else {
                $this->results[$key]['address'] = $cleanedAddress;
            }
        }
        if (!empty($business['phone'])) {
            $this->results[$key]['phone'] = preg_replace('/\s+/', ' ', $business['phone']);
        }
        
        if (!empty($business['opening_hours'])) {
            $phone = $this->extractPhoneNumbers($business['opening_hours']);
            if (!empty($phone)) {
                $this->results[$key]['opening_hours'] = trim(str_replace($phone, '', $business['opening_hours']));
                if (empty($this->results[$key]['phone'])) {
                    $this->results[$key]['phone'] = $phone;
                }
            }
            $this->results[$key]['opening_hours'] = preg_replace('/\s+/', ' ', $this->results[$key]['opening_hours']);
        }
        foreach ($this->results[$key] as $field => $value) {
            $this->results[$key][$field] = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }
    
    return $this->results;
}
    
    public function getResultCount() {
        return count($this->results);
    }
}
?>