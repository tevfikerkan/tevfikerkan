<?php
// AI Result Page Shortcode
function fursonaprints_result_page_shortcode() {
    ob_start();
    ?>
    <style>
    .loader-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 70vh;
        text-align: center;
        background: linear-gradient(to bottom, #faf9f7, #e8ded2);
        padding: 40px 20px;
    }

    .loader-video {
        max-width: 400px;
        width: 100%;
        height: auto;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(139, 105, 20, 0.2);
        margin-bottom: 30px;
    }

    .loading-text {
        font-size: 24px;
        color: #8b6914;
        font-family: Georgia, serif;
        margin-bottom: 10px;
        font-weight: 600;
    }

    .loading-subtext {
        font-size: 16px;
        color: #666;
        margin-bottom: 30px;
        font-family: Georgia, serif;
    }

    .progress-container {
        width: 100%;
        max-width: 400px;
    }

    .progress-bar {
        width: 100%;
        height: 8px;
        background: rgba(139, 105, 20, 0.1);
        border-radius: 10px;
        overflow: hidden;
        margin-bottom: 15px;
    }

    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #8b6914, #d4af37, #8b6914);
        background-size: 200% 100%;
        width: 0%;
        transition: width 0.5s ease;
        animation: shimmer 2s infinite;
    }

    @keyframes shimmer {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }

    .progress-text {
        font-size: 14px;
        color: #8b6914;
        font-family: Georgia, serif;
    }

    #ai-result {
        display: none;
        text-align: center;
        padding: 60px 20px;
        animation: fadeIn 0.8s ease-in;
        background: #faf9f7;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    #ai-result img {
        max-width: 800px;
        width: 100%;
        height: auto;
        border-radius: 15px;
        box-shadow: 0 15px 50px rgba(139, 105, 20, 0.3);
    }

    /* Mobil responsive */
    @media (max-width: 768px) {
        .loader-video {
            max-width: 280px;
        }

        .loading-text {
            font-size: 20px;
        }

        .loading-subtext {
            font-size: 14px;
        }
    }
    </style>

    <div id="ai-loading" class="loader-container">
        <video class="loader-video" autoplay loop muted playsinline>
            <source src="https://staging.fursonaprints.com/wp-content/uploads/2025/11/loader_the_painter.mp4" type="video/mp4">
            <p>Loading...</p>
        </video>

        <p class="loading-text">Your pet is becoming royalty</p>
        <p class="loading-subtext">Creating your masterpiece...</p>

        <div class="progress-container">
            <div class="progress-bar">
                <div class="progress-fill" id="progress"></div>
            </div>
            <p class="progress-text" id="progress-text">Initializing AI...</p>
        </div>
    </div>

    <div id="ai-result">
        <img id="ai-image" src="" alt="AI Generated Portrait" />
    </div>

    <script>
    (function() {
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
            console.log(`📡 API Kontrolü #${attempt}`);

            try {
                const res = await fetch(`/wp-json/myplugin/v1/check-result?job_id=${jobId}`);
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
                        imageEL.src = data.image_url;
                    }, 500);

                    return;
                }

                if (attempt >= maxAttempts) {
                    clearInterval(progressInterval);
                    progressText.innerText = '⚠️ Taking longer than expected...';
                    return;
                }

                setTimeout(checkResult, 3000);
            } catch (error) {
                console.error('❌ API Hatası:', error);
                clearInterval(progressInterval);
                progressText.innerText = '⚠️ Connection error. Please refresh.';
            }
        }

        checkResult();
    })();
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('ai_result_page', 'fursonaprints_result_page_shortcode');
