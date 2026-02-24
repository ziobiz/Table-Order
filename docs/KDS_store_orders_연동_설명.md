# KDS 조리 완료 ↔ store_orders 연동 구현 설명

## 1. 주문서가 "삭제"되는가? → 아닙니다

- **삭제(delete)되지 않습니다.** DB에서 주문 행이 지워지지 않고, **상태(status)만 바뀝니다.**
- **화면에서만** 조건에 따라 "안 보이게" 될 뿐입니다.

---

## 2. KDS(kitchen_display.php)에서의 동작

### 2-1. KDS에 주문이 보이는 조건

- `orders.store_id` = 현재 KDS의 가맹점(Store #N)
- `orders.status` ≠ `'paid'` (미결제 주문만)
- 해당 주문에 **미서빙** 메뉴가 하나라도 있음: `order_items.item_status` ≠ `'SERVED'`

→ 위 조건을 만족하는 주문만 카드로 표시됩니다.

### 2-2. "조리 완료" 버튼을 누르면

1. **항상 실행**
   - `order_items` 테이블: 해당 주문(`order_id`)의 **모든 행**의 `item_status`를 `'SERVED'`로 변경

2. **가맹점 설정이 "연동함"일 때만 추가 실행**
   - `orders` 테이블: 해당 주문의 `status`를 `'SERVED'`로 변경  
     (조건: `id` = 해당 주문 ID **그리고** `store_id` = 현재 KDS의 가맹점 ID)

3. **그 다음**
   - KDS 페이지로 리다이렉트

### 2-3. 그래서 KDS에서는 왜 주문 카드가 사라지나?

- 조리 완료 후에는 해당 주문의 **모든** `order_items`가 `item_status = 'SERVED'`가 됩니다.
- KDS는 **미서빙** 항목이 있는 주문만 보여주므로,  
  → 그 주문은 더 이상 목록에 나오지 않고 **카드가 사라진 것처럼** 보입니다.  
- DB에서 행이 삭제된 것이 아니라, **조회 조건에서 빠진 것**입니다.

---

## 3. store_orders.php에서의 동작

### 3-1. store_orders에 주문이 보이는 조건

- `orders.store_id` = 로그인한 가맹점 ID
- `orders.status` ≠ `'paid'`  
  → **대기(pending), 조리중(cooking), 서빙완료(served)** 주문까지 **전부** 표시됩니다.

### 3-2. "완료"해도 주문이 안 사라지는 이유

- KDS에서 "조리 완료"를 하면 `orders.status`가 **`SERVED`(서빙완료)** 로만 바뀝니다.
- `SERVED`는 **`paid`가 아니므로**  
  → store_orders의 조건 `status != 'paid'` 에 그대로 포함됩니다.
- 따라서 **주문 카드는 그대로 남고**,  
  - 상태 뱃지/버튼만 **"서빙완료(SERVED)"** 로 바뀌고  
  - "결제 완료" 같은 **다음 단계 버튼**이 보이게 됩니다.

### 3-3. store_orders에서 주문이 "사라지는" 시점

- 가맹점이 **"결제 완료"**(또는 동일 기능) 버튼을 눌러  
  `orders.status`가 **`'paid'`** 로 바뀔 때만  
  → `status != 'paid'` 조건에서 제외되어 **목록에서 사라집니다.**

정리하면:

- **KDS "조리 완료"** → 주문은 **SERVED**로만 바뀜 → **store_orders에는 그대로 보임** (상태만 서빙완료로 변경)
- **store_orders "결제 완료"** → 주문이 **paid**로 바뀜 → **그때 비로소 store_orders 목록에서 사라짐**

---

## 4. 기존 DB / store_orders에 변함이 없을 때 확인할 것

### 4-1. 연동 설정(stores.kds_sync_order_status)

- **컬럼**: `stores.kds_sync_order_status`
  - `1` = 연동함 (조리 완료 시 `orders.status`를 SERVED로 변경)
  - `0` = 연동 안 함 (orders는 건드리지 않음)

- **확인 방법**
  - 가맹점 관리 → **KDS & 알림 설정** → **"KDS 조리완료 ↔ 주문 상태 연동"**  
    → **"연동함"** 선택 후 **설정 저장** 한 번 더 실행
  - DB에서 직접 확인:
    ```sql
    SELECT id, store_name, kds_sync_order_status FROM stores WHERE id = 1;
    ```
    연동을 쓰려면 `kds_sync_order_status`가 `1`이어야 합니다.

- **기존 DB에 컬럼이 없는 경우**
  - `store_setting.php` 또는 `kitchen_display.php`를 한 번 열면  
    `kds_sync_order_status` 컬럼이 없을 때 **한 번만** 자동 추가됩니다.
  - 추가 후 기본값은 `1`(연동함)이지만,  
    **한 번이라도 "연동 안 함"으로 저장한 적이 있으면 0**일 수 있으므로,  
    위에서처럼 **다시 "연동함"으로 저장**해 보세요.

### 4-2. 가맹점(store_id) 일치

- KDS에 표시되는 주문은 **현재 KDS의 store_id**로 들어온 주문만입니다.
- `orders` 테이블의 각 주문은 **어느 가맹점 주문인지** `store_id`로 구분합니다.
- **조리 완료 시**  
  `UPDATE orders SET status = 'SERVED' WHERE id = ? AND store_id = ?`  
  에서 **같은 store_id**인 주문만 상태가 바뀝니다.

따라서:

- **store_orders.php** = 로그인한 가맹점(예: store_id=1)의 주문만 표시
- **KDS** = URL의 `?store_id=1`(또는 세션의 store_id)인 가맹점 주문만 표시

**서로 다른 store_id**면:

- KDS에서 조리 완료한 주문은 "다른 가맹점" 주문이라  
  → **현재 로그인한 가맹점의 store_orders에는 아무 변화가 없습니다.**

**권장 사용법**

- store_orders.php에서 **"KDS 화면 열기"** 버튼으로 KDS를 열면  
  → 같은 가맹점(store_id)으로 열리므로,  
  → 조리 완료 시 해당 가맹점의 store_orders에 상태가 반영됩니다.

### 4-3. 연동 실패 시 화면 안내

- 조리 완료 시 **연동은 켜져 있는데** `UPDATE orders`가 한 건도 갱신되지 않으면  
  (해당 주문의 `store_id`가 현재 KDS와 다를 때)
- KDS로 리다이렉트할 때 `sync_fail=1`을 붙이고,  
  KDS 상단에  
  **"조리 완료는 처리되었으나, 주문 상태 연동(store_orders 반영)이 되지 않았습니다. 해당 주문이 현재 KDS(Store #N) 소속인지 확인하세요."**  
  메시지를 띄우도록 되어 있습니다.

---

## 5. 요약 표

| 동작 | order_items | orders.status | KDS에 보임? | store_orders에 보임? |
|------|-------------|--------------|-------------|----------------------|
| 주문 접수 직후 | 일부 미서빙 | pending | ○ | ○ (대기) |
| KDS "조리 완료" | 전부 SERVED | SERVED(연동 시) | × (조건에서 제외) | ○ (서빙완료) |
| store_orders "결제 완료" | 변화 없음 | paid | × | × (목록에서 제외) |

- **완료가 되면 주문서가 "같이 삭제"되는 것이 아니라**,  
  - KDS에서는 **미서빙 조건** 때문에 카드가 사라지고,  
  - store_orders에서는 **paid가 될 때만** 목록에서 사라지도록 구현되어 있습니다.
