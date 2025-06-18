# 🔍 OLT Huawei MA5800 SNMP Web Query Tool

A web-based PHP application designed for fiber network technicians to query real-time data from ONUs connected to Huawei MA5800 OLTs using SNMP and PostgreSQL.

## 🚀 Features

- SNMP queries to Huawei MA5800 OLTs:
  - ✅ Return Power
  - ✅ Reception Power
  - ✅ Last Connection Time
  - ✅ Online/Offline Status
  - ✅ Current Internet Plan
- Modern and responsive UI (HTML + CSS + Material Icons)
- PostgreSQL integration for ONU lookup by DNI/RUC
- Power values color-coded by signal strength
- Displays SNMP command used for easy copy and reuse

## 🛠️ Technologies Used

- PHP 7+
- PostgreSQL
- SNMP (v2c)
- HTML5 / CSS3 / JavaScript
- Google Material Icons

## ⚙️ Configuration

1. Set database credentials in `conectarDB()` function.
2. Ensure SNMP is enabled on your server and Huawei OLTs.
3. `snmpget` and `snmpwalk` must be installed and accessible on the server.

## 🧪 Environment Requirements

- Apache/Nginx web server
- PHP SNMP extension enabled
- Management network access to Huawei OLTs
- SNMP read permission on OLTs (default community: `FiberPro2021`)

## 🧰 Usage

1. Enter client’s DNI or RUC.
2. Select an SNMP query (e.g., power, status).
3. View real-time results and copy the SNMP command if needed.

## 📌 Notes

- OIDs are specific to Huawei MA5800 OLTs.
- To adapt for other models or ISPs, update the OLT-to-IP mapping and OID list.

## 📜 License

This project is licensed under the MIT License.
