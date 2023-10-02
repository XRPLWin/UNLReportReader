![main workflow](https://github.com/XRPLWin/UNLReportReader/actions/workflows/main.yml/badge.svg)
[![GitHub license](https://img.shields.io/github/license/XRPLWin/UNLReportReader)](https://github.com/XRPLWin/UNLReportReader/blob/main/LICENSE)
[![Total Downloads](https://img.shields.io/packagist/dt/xrplwin/unlreportreader.svg?style=flat)](https://packagist.org/packages/xrplwin/unlreportreader)

# UNLReport Reader

Fetches UNLReports on Xahau network.

This is PHP package where you can provide flag ledger and script will parse and return final validator report. For requested ledger_index script will return final UNLReport state for that ledger_index.

## Requirements
- PHP 8.1 or higher
- [Composer](https://getcomposer.org/)

## Installation
```
composer require xrplwin/unlreportreader
```

## Ranges
When fetching ledger index report data, each ledger index % 256 is flag ledger. **At flag ledger UNLReport is still not applied**, UNLReport is applied from next ledger (next 256 ledgers including last flag ledger). See table below to understand ranges when querying specific ledger index.

| Viewing ledger_index | Applied from | Applied to |
|-|-|-|
| ... | ... | ... |
| 256 (flag) | 1 | 256 |
| ... | ... | ... |
| 510 (flag-2) | 257 | 512 |
| 511 (flag-1) | 257 | 512 |
| 512 (flag) | 257 | 512 |
| 513 (flag+1) | 513 | 768 |
| 514 (flag+2) | 513 | 768 |
| ... | ... | ... |

## Usage sample

```PHP
use XRPLWin\UNLReportReader\UNLReportReader;

$reader = new UNLReportReader('https://xahau-test.net');

$response = $reader->fetchSingle(6873344); //array
/*
array [
    "flag_ledger_index" => 6873344
    "report_range" => [6873089,6873344]
    "import_vlkey" => "E1..."
    "active_validators" => array [
        0 => array:2 [
            "?Account" => "r2..."
            "PublicKey" => "E2..."
        ]
        1 => array:2 [
            "?Account" => "r3..."
            "PublicKey" => "E3..."
        ], ...
    ]
]
*/

# response below will return report for ledger range: (6873345-256) to 6873344
$response = $reader->fetchSingle(6873342);
$response = $reader->fetchSingle(6873343);
$response = $reader->fetchSingle(6873344); //flag ledger
# response below will return report for ledger range: 6873345 to (6873344+256)
$response = $reader->fetchSingle(6873345);
$response = $reader->fetchSingle(6873346);
// ...
```

### Fetching multiple reports
This is more optimized way to fetch multiple reports than doing loop and using `fetchSingle()`, since this script uses Promises to asynchronously query node in batches, default batch limit is 10 but can be configured manually.

```PHP
use XRPLWin\UNLReportReader\UNLReportReader;

//this will set async batch limit to 5 (down from default 10)
$reader = new UNLReportReader('https://xahau-test.net',['async_batch_limit' => 5]);

$forward = true;
$limit = 2;
$response = $reader->fetchMulti(6873340, $forward, $limit); //array

//Ledger index "6873340" is between 6873345 and 6873600

/*
SAMPLE RESPONSE:
array:2 [
  0 => array:4 [
    "flag_ledger_index" => 6873344
    "report_range" => array:2 [
      0 => 6873345
      1 => 6873600
    ]
    "import_vlkey" => "ED264807102805220DA0F312E71FC2C69E1552C9C5790F6C25E3729DEB573D5860"
    "active_validators" => array:7 [
      0 => array:2 [
        "Account" => "rGhk2uLd8ShzX2Zrcgn8sQk1LWBG4jjEwf"
        "PublicKey" => "ED3ABC6740983BFB13FFD9728EBCC365A2877877D368FC28990819522300C92A69"
      ]
      1 => array:2 [
        "Account" => "rnr4kwS1VkJhvjVRuq2fbWZtEdN2HbpVVu"
        "PublicKey" => "ED49F82B2FFD537F224A1E0A10DEEFC3C25CE3882979E6B327C9F18603D21F0A22"
      ]
      2 => array:2 [
        "Account" => "rJupFrPPYgUNFBdoSqhMEJ22hiHKiZSHXQ"
        "PublicKey" => "ED79EB0F6A9F01A039235E536D19F812B55ACF540C9E22CF62C271E0D42BFF5174"
      ]
      3 => array:2 [
        "Account" => "roUo3ygV92bdhfE1v9LGpPETXvJv2kQv5"
        "PublicKey" => "ED93B2BE467CAD2F9F56FB3A82BDFF17F84B09E34232DDE8FAF2FC72382F142655"
      ]
      4 => array:2 [
        "Account" => "rGsa7f4arJ8JE9ok9LCht6jCu5xBKUKVMq"
        "PublicKey" => "ED96F581FED430E8CBE1F08B37408857001D4118D49FBB594B0BE007C2DBFFD367"
      ]
      5 => array:2 [
        "Account" => "r3htgPchiR2r8kMGzPK3Wfv3WTrpaRKjtU"
        "PublicKey" => "EDCF31B8F683345E1C49B4A1D85BF2731E55E7D6781F3D4BF45EE7ADF2D2FB3402"
      ]
      6 => array:2 [
        "Account" => "rfQtB8m51sdbWgcmddRX2mMjMpSxzX1AGr"
        "PublicKey" => "EDDF197FC59A7FAA09EB1AD60A4638BA6201DD51497B5C08A1745115098E229E0E"
      ]
    ]
  ]
  1 => array:4 [
    "flag_ledger_index" => 6873600
    "report_range" => array:2 [
      0 => 6873601
      1 => 6873856
    ]
    "import_vlkey" => "ED264807102805220DA0F312E71FC2C69E1552C9C5790F6C25E3729DEB573D5860"
    "active_validators" => array:7 [
      0 => array:2 [
        "Account" => "rGhk2uLd8ShzX2Zrcgn8sQk1LWBG4jjEwf"
        "PublicKey" => "ED3ABC6740983BFB13FFD9728EBCC365A2877877D368FC28990819522300C92A69"
      ]
      1 => array:2 [
        "Account" => "rnr4kwS1VkJhvjVRuq2fbWZtEdN2HbpVVu"
        "PublicKey" => "ED49F82B2FFD537F224A1E0A10DEEFC3C25CE3882979E6B327C9F18603D21F0A22"
      ]
      2 => array:2 [
        "Account" => "rJupFrPPYgUNFBdoSqhMEJ22hiHKiZSHXQ"
        "PublicKey" => "ED79EB0F6A9F01A039235E536D19F812B55ACF540C9E22CF62C271E0D42BFF5174"
      ]
      3 => array:2 [
        "Account" => "roUo3ygV92bdhfE1v9LGpPETXvJv2kQv5"
        "PublicKey" => "ED93B2BE467CAD2F9F56FB3A82BDFF17F84B09E34232DDE8FAF2FC72382F142655"
      ]
      4 => array:2 [
        "Account" => "rGsa7f4arJ8JE9ok9LCht6jCu5xBKUKVMq"
        "PublicKey" => "ED96F581FED430E8CBE1F08B37408857001D4118D49FBB594B0BE007C2DBFFD367"
      ]
      5 => array:2 [
        "Account" => "r3htgPchiR2r8kMGzPK3Wfv3WTrpaRKjtU"
        "PublicKey" => "EDCF31B8F683345E1C49B4A1D85BF2731E55E7D6781F3D4BF45EE7ADF2D2FB3402"
      ]
      6 => array:2 [
        "Account" => "rfQtB8m51sdbWgcmddRX2mMjMpSxzX1AGr"
        "PublicKey" => "EDDF197FC59A7FAA09EB1AD60A4638BA6201DD51497B5C08A1745115098E229E0E"
      ]
    ]
  ]
]
*/
```

### Fetching multiple reports between ledgers

```PHP
use XRPLWin\UNLReportReader\UNLReportReader;

$reader = new UNLReportReader('https://xahau-test.net');

//ledger start, ledger end
$response = $reader->fetchRange(6100000, 6200000); //array
```

## Special thanks

[@richardAH](https://github.com/richardAH) - Thank you 🙏 for in-depth explanation and support.  
[@dangell7](https://github.com/dangell7) - Thank you 🙏 for great insight.