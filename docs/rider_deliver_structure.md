# Rider / Deliver 구조 정리

## 로그인 탭 구분

| 탭 표시 | 로그인 타입 | 테이블 | 대시보드 | 용도 |
|--------|-------------|--------|----------|------|
| **본사 Rider** | `rider` | `riders` | rider_dashboard.php | 본사용 Rider (배민처럼 플랫폼 수락) |
| **가맹점 Deliver** | `driver` | `drivers` (driver_type=DELIVER) | driver_dashboard.php | 가맹점에서 생성·배차하는 Deliver |

- **본사 Rider**: 배민 구조 — 본사(플랫폼) 소속, 대기 배달 콜 수락 후 배달 진행.
- **가맹점 Deliver**: 가맹점이 store_rider_manage에서 생성, store_dispatch에서 해당 매장 주문에 수동 배차.

## DB

- **riders**: Legacy 본사 Rider (store_id 있을 수 있음, rider_dashboard에서 할당 대기/내 배달).
- **drivers**: driver_type = `HQ`(본사 Rider) 또는 `DELIVER`(가맹점 Deliver). HQ는 대기 목록 수락, DELIVER는 가맹점이 지정한 배달만.

## 본사 대시보드 (admin_dashboard)

- **본사 Rider 관리**: drivers 테이블에서 driver_type=HQ 목록 관리.
- **배달 대기 현황**: deliveries 테이블에서 status=WAITING, driver_id IS NULL — 본사 Rider가 수락 대기 중인 건.
