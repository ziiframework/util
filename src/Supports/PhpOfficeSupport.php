<?php

declare(strict_types=1);

namespace Zii\Util\Supports;

use Yii;
use PhpOffice\PhpSpreadsheet\{Spreadsheet, Style\Alignment, Writer\Exception as WriterException, Writer\Xlsx};
use yii\web\Response;

class PhpOfficeSupport
{
    public static function writeXlsx(array $rows, string $filePath): bool
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $rowIndex = 1;
        foreach ($rows as $row) {
            $mergeCells = [];
            $maxRowsCount = 1;
            $colIndex = 1;
            foreach ($row as $col) {
                if (is_array($col)) {
                    $colCount = count($col);
                    $subcolIndex = 0;
                    foreach ($col as $subcol) {
                        // TODO 这里应该做一些判断：只有 $subcol 是字符串的时候，才使用 Explicit
                        $sheet->setCellValueExplicitByColumnAndRow(
                            $colIndex,
                            $rowIndex + $subcolIndex,
                            $subcol,
                            'str'
                        );
                        $subcolIndex++;
                    }
                    if ($colCount > $maxRowsCount) {
                        $maxRowsCount = $colCount;
                    }
                } else {
                    $sheet->setCellValueExplicitByColumnAndRow($colIndex, $rowIndex, $col, 'str');
                    $mergeCells[] = ['r' => $rowIndex, 'c' => $colIndex];
                }
                $colIndex++;
            }
            // 合并单元格
            if ($maxRowsCount > 1) {
                foreach ($mergeCells as $mergeCell) {
                    $sheet->mergeCellsByColumnAndRow(
                        $mergeCell['c'],
                        $mergeCell['r'],
                        $mergeCell['c'],
                        $mergeCell['r'] + $maxRowsCount - 1
                    );
                    $sheet->getStyleByColumnAndRow($mergeCell['c'], $mergeCell['r'])
                        ->getAlignment()
                        ->setVertical(Alignment::VERTICAL_CENTER);
                }
            }
            // 首行加粗
            if ($rowIndex === 1) {
                $sheet->getStyleByColumnAndRow(1, 1, count($row),1)
                    ->getFont()
                    ->setBold(true);
            }

            $rowIndex += $maxRowsCount;
        }

        try {
            (new Xlsx($spreadsheet))->save($filePath);
        } catch (WriterException $e) {
            Yii::error($e->getMessage());
            return false;
        }

        $spreadsheet->disconnectWorksheets();

        unset($spreadsheet);

        return true;
    }

    public static function writeXlsxWithSend(array $rows, string $filePath, ?string $fileName = null): ?Response
    {
        $response = Yii::$app->response;

        if (!self::writeXlsx($rows, $filePath)) {
            return null;
        }

        $response->on(
            Yii::$app->response::EVENT_AFTER_SEND,
            function () use ($filePath): void {
                unlink($filePath);
            }
        );

        if ($fileName === null) {
            $fileName = '导出数据.' . date('Y年m月d日H时i分s秒') . '.xlsx';
        }

        return $response->sendFile($filePath, $fileName);
    }
}
