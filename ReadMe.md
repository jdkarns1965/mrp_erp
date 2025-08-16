# MRP → ERP Manufacturing System

## 📌 Overview
This project is a step-by-step build of a modular manufacturing management system.  
It starts with **MRP (Material Requirements Planning)** functionality and grows into a full **ERP (Enterprise Resource Planning)** system.  

The goal is to create a lightweight, maintainable, and mobile-friendly application tailored to a manufacturing environment.

---

## 🪜 Roadmap
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
- Machine scheduling logic  
- Production status dashboard  

### Phase 3: Purchasing & Receiving
- Purchase Orders table  
- Generate POs from shortages  
- Receiving process: update inventory  
- Alerts for late deliveries  

### Phase 4: ERP Expansion
- Finance/Accounting: invoicing, cost tracking  
- HR & Labor: scheduling operators  
- Quality Control: lot tracking, inspections  
- Customer Portal/EDI integration  

---

## ⚙️ Tech Stack
- **Backend:** PHP (OOP, simple MVC structure)  
- **Database:** MySQL  
- **Frontend:** PHP + Vanilla CSS/JS (mobile-first, lightweight)  
- **Environment:** LAMP stack (Linux, Apache, MySQL, PHP)  
- **Version Control:** GitHub  

---

## 👥 Users & Roles
- **Production Planner** → Runs MRP, schedules production.  
- **Buyer/Materials Planner** → Reviews shortages, issues POs.  
- **Inventory Coordinator** → Updates receipts, stock movements.  
- **Management** → Dashboards, reporting.  

---

## 📄 Documentation
- [PRD (Product Requirements Document)](mrp-erp-prd.md) – full requirements and scope.  
- Master Document (to be built incrementally).  

---

## 🚀 Getting Started
1. Clone this repo.  
2. Set up a LAMP environment (Linux, Apache, MySQL, PHP).  
3. Create a MySQL database and import the schema (to be added).  
4. Configure `.env` file with DB credentials.  
5. Open project in browser via local server (e.g., `http://localhost/erpmfg`).  

---

## ✅ Status
- [x] PRD created  
- [ ] Database schema (Phase 1)  
- [ ] Core MRP scripts  
- [ ] Scheduling module  
- [ ] Purchasing & receiving  
- [ ] ERP expansion modules  

---

## 📌 Author
**Sean**  
Custom ERP/MRP project for manufacturing operations.  
