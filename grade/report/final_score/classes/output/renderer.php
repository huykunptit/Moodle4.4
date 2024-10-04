<?php


namespace gradereport_final_score\output;


class renderer extends \plugin_renderer_base{

    public function test(){
        echo `<div class="grade-report">
            <h2>BẢNG ĐIỂM THÀNH PHẦN</h2>
            <p>KHOA: CƠ BẢN 1</p>
            <p>BỘ MÔN:</p>
            <p>Thí lần 1 học kỳ 1 năm học 2024-2025</p>
            
            <table class="grade-table">
                <thead>
                <tr>
                    <th>Số TT</th>
                    <th>Mã SV</th>
                    <th>Họ, đệm</th>
                    <th>Tên</th>
                    <th>Lớp</th>
                    <th>Điểm chuyên cần</th>
                    <th>Điểm TN TH</th>
                    <th>Điểm BT QTC</th>
                    <th>Điểm BTL</th>
                    <th>Ghi chú</th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td>1</td>
                    <td>K24DTQT005</td>
                    <td>Nguyễn Dương Hà</td>
                    <td>Anh</td>
                    <td>D24TXQ01-K</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                </tbody>
            </table>
            
            <div class="signatures">
                <p>Trưởng Bộ môn</p>
                <p>Ký và ghi rõ họ tên</p>
                <p>Giảng viên</p>
                <p>Ký và ghi rõ họ tên</p>
                <p>GVC. Phạm Thị Khánh</p>
            </div>
            </div>
            `;
    }
}