<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>免費 AI 畫布</title>
    <!-- 【修正點】引入正確的 Puter 官方 SDK 網址 -->
    <script src="https://js.puter.com/v2/"></script>
    <style>
        body { font-family: sans-serif; max-width: 500px; margin: 40px auto; text-align: center; }
        input { width: 300px; padding: 8px; font-size: 14px; }
        button { padding: 8px 16px; font-size: 14px; cursor: pointer; }
        #result { margin-top: 20px; min-height: 200px; display: flex; justify-content: center; align-items: center; }
        /* 確保生成的圖片不會超出螢幕 */
        #result img { max-width: 100%; height: auto; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <h1>AI 文字轉圖片</h1>
    <input type="text" id="promptInput" placeholder="輸入圖片描述，例如：A cute cat astronaut">
    <button onclick="generateImage()">生成圖片</button>
    
    <div id="result"></div>

    <script>
        async function generateImage() {
            const prompt = document.getElementById('promptInput').value;
            const resultDiv = document.getElementById('result');
            
            if (!prompt) return alert('請輸入描述！');
            resultDiv.innerHTML = "<p>圖片生成中，請稍候...<br>(首次使用會彈出登入視窗，請允許彈窗)</p>";

            try {
                // 呼叫 Puter 免費生圖接口
                const image = await puter.ai.txt2img(prompt);
                
                // 清空提示文字並插入圖片物件
                resultDiv.innerHTML = '';
                resultDiv.appendChild(image);
            } catch (error) {
                console.error("生圖出錯：", error);
                resultDiv.innerHTML = `<p style="color: red;">生成失敗：${error.message || '請開啟瀏覽器主控台 (F12) 查看完整錯誤'}</p>`;
            }
        }
    </script>
</body>
</html>
