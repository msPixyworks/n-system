/**
 * ============================================================
 *
 * [custom]
 * 
 * ============================================================
 */


/* ファイル選択 カスタマイズ */
document.addEventListener("DOMContentLoaded", () => {
    const fileInput = document.getElementById("fileInput");
    const fileName = document.getElementById("fileName");

    fileInput.addEventListener("change", () => {
        if (fileInput.files.length > 0) {
            fileName.textContent = fileInput.files[0].name;
        } else {
            fileName.textContent = "未選択";
        }
    });
});

/* 複数ファイル選択 ドラッグ＆ドロップ */
document.addEventListener("DOMContentLoaded", () => {
    const dropArea = document.getElementById("dropArea");
    const fileInput = document.getElementById("multiFileInput");
    const fileList = document.getElementById("fileList");

    if (!dropArea || !fileInput || !fileList) return;

    // クリックでファイル選択
    dropArea.addEventListener("click", () => fileInput.click());

    // ドラッグしたときの見た目変化
    dropArea.addEventListener("dragover", (e) => {
        e.preventDefault();
        dropArea.classList.add("dragover");
    });
    dropArea.addEventListener("dragleave", () => {
        dropArea.classList.remove("dragover");
    });

    // ドロップされたとき
    dropArea.addEventListener("drop", (e) => {
        e.preventDefault();
        dropArea.classList.remove("dragover");
        fileInput.files = e.dataTransfer.files; // input に反映
        showFiles(fileInput.files);
    });

    // 通常の file 選択時
    fileInput.addEventListener("change", () => {
        showFiles(fileInput.files);
    });

    // ファイル一覧表示
    function showFiles(files) {
        fileList.innerHTML = "";
        Array.from(files).forEach(file => {
            const li = document.createElement("li");
            li.textContent = file.name;
            fileList.appendChild(li);
        });
    }
});