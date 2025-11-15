<script>
(function() {
    // Sadece /ai-result/ URL'sinde çalış
    if (window.location.pathname !== '/ai-result/') return;
    
    console.log('✅ JavaScript çalışıyor!');
    
    const qp = new URLSearchParams(window.location.search);
    const jobId = qp.get('job_id');
    
    if (!jobId) {
        console.error('❌ job_id bulunamadı');
        return;
    }
    
    console.log('🔍 Job ID:', jobId);
    
    const loadingEL = document.getElementById('ai-loading');
    const resultEL = document.getElementById('ai-result');
    const imageEL = document.getElementById('ai-image');
    const progressBar = document.getElementById('progress');
    const progressText = document.getElementById('progress-text');
    
    let attempt = 0;
    const maxAttempts = 100;
    const estimatedTime = 30;
    let elapsed = 0;
    
    // Progress bar animasyonu
    const progressInterval = setInterval(() => {
        elapsed += 3;
        const percentage = Math.min((elapsed / estimatedTime) * 100, 95);
        progressBar.style.width = percentage + '%';
        
        if (elapsed <= 9) {
            progressText.innerText = 'Analyzing your pet...';
        } else if (elapsed <= 18) {
            progressText.innerText = 'Painting the portrait...';
        } else if (elapsed <= 27) {
            progressText.innerText = 'Adding aristocratic details...';
        } else {
            progressText.innerText = 'Almost ready...';
        }
        
        if (elapsed >= estimatedTime) {
            clearInterval(progressInterval);
        }
    }, 3000);
    
    async function checkResult() {
        attempt++;
        console.log(`📡 API Kontrolü #${attempt}`); // ✅ DÜZELTİLDİ - backtick doğru
        
        try {
            const res = await fetch(`/wp-json/myplugin/v1/check-result?job_id=${jobId}`); // ✅ DÜZELTİLDİ - backtick doğru
            const data = await res.json();
            
            console.log('📦 API Yanıtı:', data);
            
            if (data.status === 'ready' && data.image_url) {
                console.log('🎨 Görsel hazır!');
                
                clearInterval(progressInterval);
                progressBar.style.width = '100%';
                progressText.innerText = 'Complete! ✨';
                
                setTimeout(() => {
                    loadingEL.style.display = 'none';
                    resultEL.style.display = 'block';
                    imageEL.src = data.image_url; // ✅ WordPress URL kullanılıyor
                }, 500);
                
                return;
            }
            
            if (attempt >= maxAttempts) {
                clearInterval(progressInterval);
                progressText.innerText = '⚠️ Zaman aşımı. Lütfen sayfayı yenileyin.';
                return;
            }
            
            setTimeout(checkResult, 3000);
        } catch (error) {
            console.error('❌ API Hatası:', error);
            clearInterval(progressInterval);
            progressText.innerText = 'Teknik bir sorun oluştu.';
        }
    }
    
    checkResult();
})();