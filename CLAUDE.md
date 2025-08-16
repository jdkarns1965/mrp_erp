# 📄 Product Requirements Document (PRD)
**Project:** MRP → ERP Manufacturing System  
**Author:** Sean  
**Version:** Draft 1  

---

## 1. Objective
Build a modular manufacturing management system starting with **MRP (Material Requirements Planning)** and expanding into a full **ERP (Enterprise Resource Planning)** system. The system should begin small (materials, inventory, scheduling) and grow step by step to cover purchasing, finance, HR, quality, and customer integration.  

---

## 2. Scope (Phased Implementation)
### Phase 1: Core MRP
- Materials table (resins, inserts, packaging)  
- Products table (finished goods, part numbers)  
- BOM (Bill of Materials) table  
- Inventory table (stock levels, location, lot tracking)  
- Script to:  
  - Input customer order  
  - Explode BOM → calculate requirements  
  - Compare with inventory  
  - Show shortages  

### Phase 2: Scheduling
- Production Orders table  
- Fields: machine, start date, due date, status  
- Scheduling logic: backward (from due date) or forward (from availability)  
- Production status dashboard  

### Phase 3: Purchasing & Receiving
- Purchase Orders table  
- Generate POs based on shortages  
- Manual release of POs (Phase 1)  
- Receiving process: update inventory when materials arrive  
- Alerts for late deliveries/shortages  

### Phase 4: ERP Expansion
- Finance/Accounting: link shipments → invoices  
- HR & Labor: assign shifts/operators  
- Quality Control: lot tracking, inspections, certifications  
- Customer Portal/EDI integration  

---

## 3. Users & Roles
- **Production Planner:** Runs MRP, schedules production.  
- **Buyer/Materials Planner:** Reviews shortages, issues POs, manages suppliers.  
- **Inventory Coordinator:** Updates receipts, stock movements.  
- **Management:** Access dashboards, reports.  

---

## 4. System Requirements
- **Tech Stack:**  
  - LAMP (Linux, Apache, MySQL, PHP)  
  - Frontend: PHP + vanilla CSS/JS (lightweight, mobile-first)  
  - OOP PHP with simple MVC-style structure (to keep maintainable)  
- **Environment:**  
  - Local dev on WSL2 + VS Code  
  - MySQL as database  
  - GitHub for version control  

---

## 5. Functional Requirements
- Input customer order → calculate material needs.  
- Compare BOM needs vs inventory → flag shortages.  
- Generate PO suggestions for shortages.  
- Track POs and receipts into inventory.  
- Schedule production orders with machine assignments.  
- Provide dashboards for production, inventory, and purchasing.  

---

## 6. Non-Functional Requirements
- Lightweight & mobile-friendly (works on phones/tablets).  
- Modular — each phase builds on the last.  
- Maintainable code with clear OOP structure.  
- Documentation (Master Document) updated incrementally.  

---

## 7. Deliverables
- Database schema (MySQL).  
- PHP classes for database interaction, inventory, products, materials, BOM, etc.  
- Web forms/pages for data entry and dashboards.  
- Documentation (PRD, Master Document, user guides).  

---

## 8. Roadmap
- **Phase 1 (Month 1–2):** Core MRP (materials, BOM, inventory).  
- **Phase 2 (Month 2–3):** Scheduling (production orders).  
- **Phase 3 (Month 3–4):** Purchasing & receiving.  
- **Phase 4 (Month 4+):** ERP expansion modules.  
