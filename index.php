<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'GoogleScraper.php';
    
    try {
        $keyword = $_POST['keyword'] ?? '';
        $location = $_POST['location'] ?? '';
        $pages = intval($_POST['pages'] ?? 1);
        
        if (empty($keyword) || empty($location)) {
            throw new Exception("Anahtar kelime ve konum alanları zorunludur.");
        }
        
        $proxyList = [
          "proxy_ip1:port:kullanici:sifre",
          "proxy_ip2:port:kullanici:sifre",
          "proxy_ip3:port:kullanici:sifre",
          // Daha fazla proxy ekleyebilirsiniz
        ];
        
        shuffle($proxyList);
        
        $scraper = new GoogleScraper($proxyList[0]);
        $results = $scraper->searchBusinesses($keyword, $location, $pages);
        $scraper->cleanResults();
        
        if ($scraper->getResultCount() > 0) {
            $timestamp = date('Y-m-d_H-i-s');
            $csvFile = "results_{$timestamp}.csv";
            $excelFile = "results_excel_{$timestamp}.csv";
            if ($scraper->saveToCSV($csvFile) && $scraper->saveToExcelCSV($excelFile)) {
                $message = [
                    'type' => 'success',
                    'text' => "Sonuçlar başarıyla kaydedildi. " .
                             "<a href='$csvFile' download>CSV dosyasını indir</a> | " .
                             "<a href='$excelFile' download>Excel CSV dosyasını indir</a>"
                ];
            } else {
                throw new Exception("Dosyalar kaydedilirken bir hata oluştu.");
            }
        } else {
            $message = [
                'type' => 'warning',
                'text' => "Hiçbir sonuç bulunamadı."
            ];
        }
    } catch (Exception $e) {
        $message = [
            'type' => 'error',
            'text' => "Hata: " . $e->getMessage()
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Google İşletme Sorgulama</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4F46E5;
            --primary-dark: #4338CA;
            --primary-light: #818CF8;
            --primary-gradient: linear-gradient(135deg, #4F46E5 0%, #8B5CF6 100%);
            --success-color: #10B981;
            --success-light: #D1FAE5;
            --warning-color: #F59E0B;
            --warning-light: #FEF3C7;
            --error-color: #EF4444;
            --error-light: #FEE2E2;
            --surface-color: #ffffff;
            --background-color: #F5F7FA;
            --background-gradient: linear-gradient(135deg, #F5F7FA 0%, #EEF1F5 100%);
            --text-primary: #1F2937;
            --text-secondary: #6B7280;
            --border-color: #E5E7EB;
            --border-radius: 16px;
            --box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
            --transition: all 0.4s cubic-bezier(0.215, 0.610, 0.355, 1.000);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            color: var(--text-primary);
            background: var(--background-gradient);
            padding: 0;
            min-height: 100vh;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem;
        }

        .header {
            text-align: center;
            margin-bottom: 3rem;
            animation: fadeInDown 0.8s ease forwards;
        }

        .header h1 {
            color: var(--primary-color);
            font-size: 2.5rem;
            margin-bottom: 1rem;
            font-weight: 700;
            letter-spacing: -0.5px;
            position: relative;
            display: inline-block;
        }

        .header h1:after {
            content: '';
            position: absolute;
            width: 50%;
            height: 4px;
            background: var(--primary-gradient);
            bottom: -6px;
            left: 25%;
            border-radius: 2px;
            transform: scaleX(0);
            transform-origin: center;
            animation: scaleIn 0.8s 0.5s ease forwards;
        }

        .header p {
            color: var(--text-secondary);
            font-size: 1.1rem;
            font-weight: 300;
            max-width: 600px;
            margin: 0 auto;
        }

        .card {
            background-color: var(--surface-color);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 2.5rem;
            margin-bottom: 2rem;
            transition: var(--transition);
            animation: fadeInUp 0.8s 0.3s ease both;
            position: relative;
            overflow: hidden;
        }

        .card:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: var(--primary-gradient);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.6s cubic-bezier(0.215, 0.610, 0.355, 1.000);
        }

        .card:hover {
            box-shadow: 0 20px 30px -10px rgba(0,0,0,0.18), 0 10px 10px -5px rgba(0,0,0,0.04);
            transform: translateY(-5px);
        }

        .card:hover:before {
            transform: scaleX(1);
        }

        .form-group {
            margin-bottom: 1.8rem;
        }

        label {
            display: block;
            margin-bottom: 0.7rem;
            font-weight: 500;
            color: var(--text-primary);
            font-size: 1rem;
            transition: var(--transition);
        }

        .input-group {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 1.2rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            transition: var(--transition);
            font-size: 1.1rem;
        }

        input, select {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            border: 2px solid var(--border-color);
            border-radius: calc(var(--border-radius) / 2);
            font-size: 1rem;
            font-family: 'Poppins', sans-serif;
            transition: var(--transition);
            background-color: var(--surface-color);
            color: var(--text-primary);
        }

        input:focus, select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
        }

        input:focus + .input-icon,
        select:focus + .input-icon {
            color: var(--primary-color);
        }

        select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%236B7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1.2rem;
            padding-right: 2.5rem;
        }

        .btn {
            display: inline-block;
            font-weight: 600;
            text-align: center;
            white-space: nowrap;
            vertical-align: middle;
            user-select: none;
            border: none;
            padding: 1rem 1.5rem;
            font-size: 1.1rem;
            line-height: 1.5;
            border-radius: calc(var(--border-radius) / 2);
            transition: var(--transition);
            cursor: pointer;
            width: 100%;
            font-family: 'Poppins', sans-serif;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }

        .btn-primary {
            color: #fff;
            background: var(--primary-gradient);
            border: none;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(79, 70, 229, 0.4);
        }

        .btn-primary:active {
            transform: translateY(0);
            box-shadow: 0 5px 10px -5px rgba(79, 70, 229, 0.2);
        }

        .btn-primary:disabled {
            background: var(--text-secondary);
            cursor: not-allowed;
            opacity: 0.65;
            transform: translateY(0);
            box-shadow: none;
        }

        .btn-primary:after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 5px;
            height: 5px;
            background: rgba(255, 255, 255, 0.5);
            opacity: 0;
            border-radius: 100%;
            transform: scale(1, 1) translate(-50%);
            transform-origin: 50% 50%;
            z-index: -1;
        }

        .btn-primary:focus:not(:active)::after {
            animation: ripple 1s ease-out;
        }

        .message {
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: none;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            animation: fadeInUp 0.8s 0.1s ease both;
            transition: var(--transition);
            transform: translateY(0);
        }

        .message-icon {
            font-size: 1.8rem;
            margin-right: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
        }

        .success {
            background-color: var(--success-light);
            color: var(--success-color);
            box-shadow: 0 10px 15px -5px rgba(16, 185, 129, 0.1);
        }

        .warning {
            background-color: var(--warning-light);
            color: var(--warning-color);
            box-shadow: 0 10px 15px -5px rgba(245, 158, 11, 0.1);
        }

        .error {
            background-color: var(--error-light);
            color: var(--error-color);
            box-shadow: 0 10px 15px -5px rgba(239, 68, 68, 0.1);
        }

        .message a {
            color: inherit;
            text-decoration: none;
            font-weight: 600;
            margin-left: 5px;
            padding: 0.4rem 0.8rem;
            border-radius: calc(var(--border-radius) / 2);
            background-color: rgba(255, 255, 255, 0.5);
            transition: var(--transition);
            border: 1px solid currentColor;
            display: inline-block;
            margin-top: 0.5rem;
        }

        .message a:hover {
            background-color: rgba(255, 255, 255, 0.8);
            transform: translateY(-2px);
            box-shadow: 0 5px 10px -5px rgba(0, 0, 0, 0.1);
        }

        .loading {
            display: none;
            text-align: center;
            padding: 2rem;
            animation: fadeIn 0.5s ease both;
        }

        .spinner {
            display: inline-block;
            width: 60px;
            height: 60px;
            position: relative;
        }

        .spinner:before, .spinner:after {
            content: '';
            display: block;
            position: absolute;
            border-radius: 50%;
            border: 3px solid transparent;
            border-top-color: var(--primary-color);
            animation: spin 1.5s cubic-bezier(0.215, 0.610, 0.355, 1.000) infinite;
        }

        .spinner:before {
            width: 100%;
            height: 100%;
            border-width: 3px;
        }

        .spinner:after {
            width: 80%;
            height: 80%;
            top: 10%;
            left: 10%;
            border-width: 3px;
            animation-duration: 1s;
            border-top-color: var(--primary-light);
        }

        .loading-text {
            margin-top: 1.2rem;
            color: var(--text-secondary);
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .loading-dots:after {
            content: '';
            animation: loadingDots 1.5s infinite;
        }

        .footer {
            text-align: center;
            margin-top: 3rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
            color: var(--text-secondary);
            font-size: 0.9rem;
            animation: fadeInUp 0.8s 0.5s ease both;
        }

        .excel-guide {
            margin-top: 2rem;
            padding: 1.8rem;
            background-color: rgba(16, 185, 129, 0.05);
            border-radius: var(--border-radius);
            border-left: 4px solid var(--success-color);
            animation: fadeInUp 0.8s 0.2s ease both;
            box-shadow: 0 10px 15px -5px rgba(16, 185, 129, 0.1);
        }

        .excel-guide h3 {
            color: var(--success-color);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            font-size: 1.2rem;
        }

        .excel-guide h3 i {
            margin-right: 0.7rem;
        }

        .excel-guide ol {
            padding-left: 1.5rem;
        }

        .excel-guide li {
            margin-bottom: 0.7rem;
            position: relative;
            padding-left: 0.5rem;
        }

        .excel-guide strong {
            color: var(--primary-color);
        }

        @media (max-width: 768px) {
            .container {
                padding: 1.5rem;
            }
            
            .card {
                padding: 1.8rem;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .header p {
                font-size: 1rem;
            }
            
            input, select, .btn {
                padding: 0.9rem;
                font-size: 0.95rem;
            }
            
            input, select {
                padding-left: 2.7rem;
            }
            
            .input-icon {
                left: 1rem;
            }
            
            .message {
                flex-direction: column;
                text-align: center;
            }
            
            .message-icon {
                margin-right: 0;
                margin-bottom: 0.8rem;
            }
            
            .message a {
                display: block;
                margin: 0.7rem auto 0;
                width: 100%;
                max-width: 250px;
            }
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        @keyframes scaleIn {
            from {
                transform: scaleX(0);
            }
            to {
                transform: scaleX(1);
            }
        }

        @keyframes spin {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
        }

        @keyframes loadingDots {
            0%, 20% { content: '.'; }
            40% { content: '..'; }
            60% { content: '...'; }
            80% { content: '....'; }
            100% { content: '.....'; }
        }

        @keyframes ripple {
            0% {
                transform: scale(0, 0);
                opacity: 0.5;
            }
            20% {
                transform: scale(25, 25);
                opacity: 0.5;
            }
            100% {
                opacity: 0;
                transform: scale(40, 40);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-search"></i> Google İşletme Sorgulama</h1>
            <p>Anahtar kelime ve konum belirterek Google'da işletme bilgilerini hızlıca bulun ve dışa aktarın</p>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="message <?= $message['type'] ?>">
                <div class="message-icon">
                    <?php if($message['type'] === 'success'): ?>
                        <i class="fas fa-check-circle"></i>
                    <?php elseif($message['type'] === 'warning'): ?>
                        <i class="fas fa-exclamation-triangle"></i>
                    <?php elseif($message['type'] === 'error'): ?>
                        <i class="fas fa-times-circle"></i>
                    <?php endif; ?>
                </div>
                <div>
                    <?= $message['text'] ?>
                </div>
            </div>
            
            <?php if($message['type'] === 'success'): ?>
            <div class="excel-guide">
                <h3><i class="fas fa-file-excel"></i> Excel'de CSV Dosyasını Açma Kılavuzu</h3>
                <ol>
                    <li><strong>Excel'i açın</strong> ve yeni boş bir çalışma kitabı oluşturun.</li>
                    <li><strong>Veri</strong> sekmesine tıklayın.</li>
                    <li><strong>Metinden/CSV'den Al</strong> (veya <strong>Dış Veri Al</strong>) seçeneğine tıklayın.</li>
                    <li>İndirdiğiniz CSV dosyasını seçin.</li>
                    <li>Açılan sihirbazda <strong>Sınırlayıcı</strong> olarak <strong>Sekme</strong> seçin.</li>
                    <li><strong>UTF-8</strong> kodlamasını seçin.</li>
                    <li><strong>Yükle</strong> düğmesine tıklayın.</li>
                </ol>
                <p>Excel CSV dosyası özellikle Excel ile kullanım için optimize edilmiştir ve doğrudan açılmalıdır.</p>
            </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="card">
            <form method="post" action="" id="searchForm">
                <div class="form-group">
                    <label for="keyword">Anahtar Kelime</label>
                    <div class="input-group">
                        <i class="fas fa-tag input-icon"></i>
                        <input 
                            type="text" 
                            id="keyword" 
                            name="keyword" 
                            placeholder="Ör: Süt Ürünleri, Restoran, Berber" 
                            required
                            value="<?= htmlspecialchars($_POST['keyword'] ?? '') ?>"
                        >
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="location">Konum</label>
                    <div class="input-group">
                        <i class="fas fa-map-marker-alt input-icon"></i>
                        <input 
                            type="text" 
                            id="location" 
                            name="location" 
                            placeholder="Ör: Mersin, Ankara, İstanbul/Kadıköy" 
                            required
                            value="<?= htmlspecialchars($_POST['location'] ?? '') ?>"
                        >
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="pages">Sonuç Sayfası Sayısı</label>
                    <div class="input-group">
                        <i class="fas fa-file-alt input-icon"></i>
                        <select id="pages" name="pages" required>
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <option value="<?= $i ?>" <?= (isset($_POST['pages']) && $_POST['pages'] == $i) ? 'selected' : '' ?>>
                                    <?= $i ?> Sayfa (yaklaşık <?= $i * 20 ?> sonuç)
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                
                <button type="submit" id="submitButton" class="btn btn-primary">
                    <i class="fas fa-search"></i> Sorgulama Başlat
                </button>
            </form>
        </div>
        
        <div id="loading" class="loading">
            <div class="spinner"></div>
            <div class="loading-text">
                Sonuçlar aranıyor<span class="loading-dots"></span>
            </div>
        </div>
        
        <div class="footer">
            <p>&copy; <?= date('Y') ?> Google İşletme Sorgulama. Tüm hakları saklıdır.</p>
            <p><small>Bu uygulama, eğitim ve bilgi amaçlı geliştirilmiştir.</small></p>
        </div>
    </div>

    <script>
        document.getElementById('searchForm').addEventListener('submit', function(e) {
            const submitButton = document.getElementById('submitButton');
            const loading = document.getElementById('loading');
            
            submitButton.disabled = true;
            loading.style.display = 'block';
            
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    </script>
</body>
</html>